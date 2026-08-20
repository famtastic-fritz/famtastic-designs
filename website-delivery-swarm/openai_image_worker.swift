#!/usr/bin/env swift
// FAMtastic's direct, image-only GPT Image 2 worker.
// The API key stays inside the macOS Security framework; this code calls only
// POST /v1/images/generations and only accepts the exact gpt-image-2 model.

import Foundation
import Security
import CryptoKit

let model = "gpt-image-2"
let apiBase = "https://api.openai.com/v1"
let keychainService = "FAMtastic.OpenAI.Image"
let keychainAccount = "famtastic-image-worker"
let defaultSize = "1536x1024"
let allowedQuality = "high"
let imageOutputEstimateUSD = 0.165
let promptInputReserveUSD = 0.015
let maximumImages = 6

struct WorkerError: Error, LocalizedError {
    let message: String
    var errorDescription: String? { message }
}

struct Prompt {
    let directionID: String
    let prompt: String
    let filename: String
    let size: String
    let estimatedCostUSD: Double
}

struct Options {
    var promptsPath: String?
    var outputPath: String?
    var requestID: String?
    var execute = false
    var maxCostUSD: Double?
    var preflight = false
}

func utcNow() -> String { ISO8601DateFormatter().string(from: Date()) }

func sha256(_ data: Data) -> String {
    SHA256.hash(data: data).map { String(format: "%02x", $0) }.joined()
}

func parseOptions() throws -> Options {
    let values = Array(CommandLine.arguments.dropFirst())
    var options = Options()
    var index = 0
    func nextValue(_ name: String) throws -> String {
        guard index + 1 < values.count else { throw WorkerError(message: "Missing value for \(name)") }
        index += 1
        return values[index]
    }
    while index < values.count {
        switch values[index] {
        case "--prompts": options.promptsPath = try nextValue("--prompts")
        case "--output": options.outputPath = try nextValue("--output")
        case "--request-id": options.requestID = try nextValue("--request-id")
        case "--execute": options.execute = true
        case "--max-cost-usd":
            let raw = try nextValue("--max-cost-usd")
            guard let value = Double(raw), value > 0 else { throw WorkerError(message: "--max-cost-usd must be positive") }
            options.maxCostUSD = value
        case "--preflight": options.preflight = true
        case "--help", "-h":
            print("Usage: openai_image_worker.swift --preflight | --prompts <json> --output <dir> [--request-id <id>] [--execute --max-cost-usd <usd>]")
            exit(0)
        default: throw WorkerError(message: "Unknown option: \(values[index])")
        }
        index += 1
    }
    if options.preflight && (options.promptsPath != nil || options.outputPath != nil || options.execute || options.maxCostUSD != nil) {
        throw WorkerError(message: "--preflight cannot be combined with image generation arguments")
    }
    return options
}

func keychainSecret() throws -> String {
    let query: [String: Any] = [
        kSecClass as String: kSecClassGenericPassword,
        kSecAttrService as String: keychainService,
        kSecAttrAccount as String: keychainAccount,
        kSecReturnData as String: true,
        kSecMatchLimit as String: kSecMatchLimitOne,
    ]
    var result: CFTypeRef?
    let status = SecItemCopyMatching(query as CFDictionary, &result)
    guard status == errSecSuccess,
          let data = result as? Data,
          let secret = String(data: data, encoding: .utf8),
          !secret.isEmpty else {
        throw WorkerError(message: "The FAMtastic image-only Keychain credential is unavailable")
    }
    return secret
}

func send(_ request: URLRequest, timeout: TimeInterval) throws -> (Data, Int) {
    let semaphore = DispatchSemaphore(value: 0)
    var receivedData = Data()
    var status = -1
    var receivedError: Error?
    let configuration = URLSessionConfiguration.ephemeral
    configuration.timeoutIntervalForRequest = timeout
    configuration.timeoutIntervalForResource = timeout + 30
    URLSession(configuration: configuration).dataTask(with: request) { data, response, error in
        receivedData = data ?? Data()
        status = (response as? HTTPURLResponse)?.statusCode ?? -1
        receivedError = error
        semaphore.signal()
    }.resume()
    guard semaphore.wait(timeout: .now() + timeout) == .success else { throw WorkerError(message: "OpenAI image request timed out") }
    if let receivedError, status < 0 {
        let networkError = receivedError as NSError
        throw WorkerError(message: "OpenAI image request could not reach the API (network code \(networkError.code))")
    }
    return (receivedData, status)
}

func modelPreflight() throws {
    let secret = try keychainSecret()
    var request = URLRequest(url: URL(string: "\(apiBase)/models/\(model)")!)
    request.httpMethod = "GET"
    request.setValue("Bearer \(secret)", forHTTPHeaderField: "Authorization")
    let (_, status) = try send(request, timeout: 20)
    guard status == 200 else { throw WorkerError(message: "OpenAI model preflight returned HTTP \(status)") }
}

func loadPrompts(_ path: String) throws -> [Prompt] {
    let data = try Data(contentsOf: URL(fileURLWithPath: path))
    let decoded = try JSONSerialization.jsonObject(with: data)
    let raw: [[String: Any]]?
    if let direct = decoded as? [[String: Any]] {
        raw = direct
    } else if let document = decoded as? [String: Any] {
        raw = document["prompts"] as? [[String: Any]]
    } else {
        raw = nil
    }
    guard let raw, !raw.isEmpty else {
        throw WorkerError(message: "Image prompt artifact must be a non-empty JSON array")
    }
    guard raw.count <= maximumImages else { throw WorkerError(message: "Image request exceeds the hard cap of \(maximumImages) images") }
    var names = Set<String>()
    return try raw.enumerated().map { offset, item in
        let prompt = (item["prompt"] as? String) ?? (item["prompt_verbatim"] as? String)
        let directionID = (item["direction_id"] as? String) ?? (item["prompt_id"] as? String)
        guard let prompt, !prompt.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
              let directionID, !directionID.isEmpty else {
            throw WorkerError(message: "Prompt \(offset + 1) needs a direction_id and prompt")
        }
        let filename = (item["filename"] as? String) ?? "\(directionID).png"
        guard filename.hasSuffix(".png"), !filename.contains("/"), !filename.contains("\\"), filename != ".png" else {
            throw WorkerError(message: "Prompt \(offset + 1) filename must be a simple .png filename")
        }
        guard names.insert(filename).inserted else { throw WorkerError(message: "Duplicate image target: \(filename)") }
        let aspectRatio = (item["params"] as? [String: Any])?["aspectRatio"] as? String
        let sourceAspectSize = ["16:9": "2304x1296", "4:5": "1344x1680"][aspectRatio ?? ""]
        let size = (item["size"] as? String) ?? sourceAspectSize ?? defaultSize
        let parts = size.split(separator: "x")
        guard parts.count == 2, let width = Int(parts[0]), let height = Int(parts[1]),
              width <= 3840, height <= 3840, width % 16 == 0, height % 16 == 0,
              width * height >= 655_360, width * height <= 8_294_400,
              Double(max(width, height)) / Double(min(width, height)) <= 3 else {
            throw WorkerError(message: "Prompt \(offset + 1) has an invalid GPT Image 2 size: \(size)")
        }
        let estimate = (item["estimated_cost_usd"] as? NSNumber)?.doubleValue
            ?? (size == defaultSize ? imageOutputEstimateUSD + promptInputReserveUSD : 0.5)
        guard estimate > 0 else { throw WorkerError(message: "Prompt \(offset + 1) needs a positive cost estimate") }
        return Prompt(directionID: directionID, prompt: prompt.trimmingCharacters(in: .whitespacesAndNewlines), filename: filename, size: size, estimatedCostUSD: estimate)
    }
}

func receiptBase(_ prompts: [Prompt], requestID: String?) -> [String: Any] {
    let estimate = prompts.reduce(0) { $0 + $1.estimatedCostUSD }
    var receipt: [String: Any] = [
        "schema": "famtastic.openai-image-generation-receipt.v1",
        "generated_at": utcNow(),
        "provider": "openai_image_api",
        "transport": "https://api.openai.com/v1/images/generations",
        "model": model,
        "model_allowlist": [model],
        "endpoint_allowlist": ["POST /v1/images/generations"],
        "denied_capabilities": ["text_generation", "research", "code_generation", "embeddings", "audio", "video"],
        "quality": allowedQuality,
        "image_count": prompts.count,
        "estimated_cost_usd": (estimate * 1000).rounded() / 1000,
        "estimate_components_usd": ["default_image_output_each": imageOutputEstimateUSD, "default_prompt_input_reserve_each": promptInputReserveUSD],
        "prompts": prompts.map { item in
            ["direction_id": item.directionID, "filename": item.filename, "size": item.size, "estimated_cost_usd": item.estimatedCostUSD, "prompt": item.prompt, "prompt_sha256": sha256(Data(item.prompt.utf8))]
        },
    ]
    if let requestID { receipt["request_id"] = requestID } else { receipt["request_id"] = NSNull() }
    return receipt
}

func writeJSON(_ value: [String: Any], to url: URL) throws {
    let data = try JSONSerialization.data(withJSONObject: value, options: [.prettyPrinted, .sortedKeys])
    try data.write(to: url, options: .atomic)
}

func generate(secret: String, prompt: String, size: String) throws -> (Data, [String: Any]) {
    let body: [String: Any] = ["model": model, "prompt": prompt, "n": 1, "size": size, "quality": allowedQuality]
    var request = URLRequest(url: URL(string: "\(apiBase)/images/generations")!)
    request.httpMethod = "POST"
    request.httpBody = try JSONSerialization.data(withJSONObject: body)
    request.timeoutInterval = 300
    request.setValue("Bearer \(secret)", forHTTPHeaderField: "Authorization")
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    let (data, status) = try send(request, timeout: 180)
    guard status == 200 else { throw WorkerError(message: "OpenAI image request failed with HTTP \(status)") }
    guard let payload = try JSONSerialization.jsonObject(with: data) as? [String: Any],
          let entries = payload["data"] as? [[String: Any]],
          let first = entries.first,
          let encoded = first["b64_json"] as? String,
          let image = Data(base64Encoded: encoded), image.count > 10_000,
          image.prefix(8) == Data([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]) else {
        throw WorkerError(message: "OpenAI image response did not contain a valid PNG artifact")
    }
    var metadata: [String: Any] = [:]
    if let revisedPrompt = first["revised_prompt"] { metadata["revised_prompt"] = revisedPrompt }
    if let usage = payload["usage"] { metadata["api_usage"] = usage }
    return (image, metadata)
}

func run() throws {
    let options = try parseOptions()
    if options.preflight {
        try modelPreflight()
        print("OPENAI_IMAGE_PREFLIGHT_AUTHENTICATED")
        return
    }
    guard let promptsPath = options.promptsPath, let outputPath = options.outputPath else {
        throw WorkerError(message: "--prompts and --output are required for image generation")
    }
    let prompts = try loadPrompts(promptsPath)
    let output = URL(fileURLWithPath: outputPath, isDirectory: true)
    try FileManager.default.createDirectory(at: output, withIntermediateDirectories: true)
    let receiptURL = output.appendingPathComponent("generation-receipt.json")
    for prompt in prompts where FileManager.default.fileExists(atPath: output.appendingPathComponent(prompt.filename).path) {
        throw WorkerError(message: "Refusing to overwrite existing image artifact: \(prompt.filename)")
    }
    var receipt = receiptBase(prompts, requestID: options.requestID)
    if !options.execute {
        receipt["status"] = "dry_run"
        receipt["charge_authorized"] = false
        receipt["notes"] = ["No image API request was made. Re-run with --execute and a sufficient --max-cost-usd."]
        try writeJSON(receipt, to: receiptURL)
        let estimate = receipt["estimated_cost_usd"] as! Double
        print(String(format: "OPENAI_IMAGE_DRY_RUN estimate_usd=%.3f", estimate))
        return
    }
    guard let ceiling = options.maxCostUSD else { throw WorkerError(message: "Real image generation requires --max-cost-usd") }
    let estimate = receipt["estimated_cost_usd"] as! Double
    guard estimate <= ceiling else {
        throw WorkerError(message: String(format: "Estimated image cost $%.3f exceeds declared ceiling $%.3f", estimate, ceiling))
    }
    let secret = try keychainSecret()
    receipt["status"] = "running"
    receipt["charge_authorized"] = true
    receipt["max_cost_usd"] = ceiling
    var artifacts: [[String: Any]] = []
    let started = Date()
    for prompt in prompts {
        let imageStarted = Date()
        let (image, metadata) = try generate(secret: secret, prompt: prompt.prompt, size: prompt.size)
        let target = output.appendingPathComponent(prompt.filename)
        try image.write(to: target, options: .atomic)
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: target.path)
        var artifact: [String: Any] = ["direction_id": prompt.directionID, "path": prompt.filename, "size": prompt.size, "bytes": image.count, "sha256": sha256(image), "duration_ms": max(1, Int(Date().timeIntervalSince(imageStarted) * 1000))]
        metadata.forEach { artifact[$0.key] = $0.value }
        artifacts.append(artifact)
    }
    receipt["status"] = "complete"
    receipt["duration_ms"] = max(1, Int(Date().timeIntervalSince(started) * 1000))
    receipt["artifacts"] = artifacts
    receipt["actual_cost_usd"] = NSNull()
    receipt["actual_cost_status"] = "provider_did_not_return_invoice_cost; retain estimate and reconcile with OpenAI usage"
    try writeJSON(receipt, to: receiptURL)
    print("OPENAI_IMAGE_GENERATION_COMPLETE images=\(prompts.count) receipt=\(receiptURL.path)")
}

do {
    try run()
} catch {
    fputs("OPENAI_IMAGE_WORKER_ERROR: \(error.localizedDescription)\n", stderr)
    exit(2)
}
