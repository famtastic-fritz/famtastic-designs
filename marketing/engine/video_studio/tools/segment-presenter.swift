import AVFoundation
import CoreImage
import CoreVideo
import Darwin
import Foundation
import ImageIO
import UniformTypeIdentifiers
import Vision

struct FrameEvidence: Encodable {
    let frameIndex: Int
    let presentationTimeSeconds: Double
    let inferenceMilliseconds: Double
    let maskWidth: Int
    let maskHeight: Int
    let componentsAtThreshold: Int
    let mainComponentPixels: Int
    let alphaPixelsBefore: Int
    let alphaPixelsAfter: Int
    let removedAlphaPixels: Int
    let maskPath: String
    let previewPath: String?
}

struct RunEvidence: Encodable {
    let inputPath: String
    let inputDurationSeconds: Double
    let inputWidth: Int
    let inputHeight: Int
    let inputFrameRate: Double
    let mode: String
    let sampleTimesSeconds: [Double]
    let componentThreshold: UInt8
    let softRadiusPixels: Int
    let visionRequest: String
    let visionOutputPixelFormat: String
    let frameCount: Int
    let maximumResidentSetBytes: Int
    let frames: [FrameEvidence]
}

struct Options {
    let input: URL
    let outputDirectory: URL
    let sampleTimes: [Double]?
    let componentThreshold: UInt8
    let softRadius: Int
    let writePreviews: Bool

    static func parse(_ arguments: [String]) throws -> Options {
        let valueOptions: Set<String> = [
            "--input", "--output-dir", "--sample-times", "--all-frames",
            "--component-threshold", "--soft-radius"
        ]
        var values: [String: String] = [:]
        var flags = Set<String>()
        var index = 1
        while index < arguments.count {
            let key = arguments[index]
            if key == "--no-previews" {
                guard !flags.contains(key) else { throw usageError("Duplicate argument: \(key)") }
                flags.insert(key)
                index += 1
                continue
            }
            guard valueOptions.contains(key) else {
                throw usageError("Unknown argument: \(key)")
            }
            guard index + 1 < arguments.count, !arguments[index + 1].hasPrefix("--") else {
                throw usageError("Invalid or incomplete argument: \(key)")
            }
            guard values[key] == nil else { throw usageError("Duplicate argument: \(key)") }
            values[key] = arguments[index + 1]
            index += 2
        }

        guard let inputText = values["--input"],
              let outputText = values["--output-dir"] else {
            throw usageError("--input and --output-dir are required")
        }
        let sampleTimes: [Double]?
        if let value = values["--sample-times"] {
            guard values["--all-frames"] == nil else {
                throw usageError("Choose either --sample-times or --all-frames true, not both")
            }
            let parsed = try value.split(separator: ",", omittingEmptySubsequences: false).map { token -> Double in
                guard let seconds = Double(token), seconds.isFinite, seconds >= 0 else {
                    throw usageError("Sample times must be finite, non-negative seconds")
                }
                return seconds
            }
            guard !parsed.isEmpty, parsed == parsed.sorted(), Set(parsed).count == parsed.count else {
                throw usageError("--sample-times must be non-empty, ordered, and unique")
            }
            sampleTimes = parsed
        } else if let allFramesValue = values["--all-frames"] {
            guard allFramesValue == "true" else {
                throw usageError("--all-frames accepts only the value true")
            }
            sampleTimes = nil
        } else {
            throw usageError("Choose --sample-times 2,6,... or --all-frames true")
        }

        guard let thresholdValue = Int(values["--component-threshold"] ?? "32"), (1...255).contains(thresholdValue) else {
            throw usageError("--component-threshold must be 1...255")
        }
        let threshold = UInt8(thresholdValue)
        guard let radius = Int(values["--soft-radius"] ?? "6") else {
            throw usageError("--soft-radius must be an integer")
        }
        guard radius >= 0 && radius <= 64 else { throw usageError("--soft-radius must be 0...64") }
        return Options(
            input: URL(fileURLWithPath: inputText).standardizedFileURL,
            outputDirectory: URL(fileURLWithPath: outputText, isDirectory: true).standardizedFileURL,
            sampleTimes: sampleTimes,
            componentThreshold: threshold,
            softRadius: radius,
            writePreviews: !flags.contains("--no-previews")
        )
    }

    static func usageError(_ message: String) -> NSError {
        NSError(domain: "SegmentPresenter", code: 2, userInfo: [
            NSLocalizedDescriptionKey: "\(message)\nUsage: segment-presenter --input source.mp4 --output-dir OUTPUT (--sample-times 2,6,10,18,26 | --all-frames true) [--component-threshold 32] [--soft-radius 6] [--no-previews]"
        ])
    }
}

struct CleanupResult {
    let pixels: [UInt8]
    let componentCount: Int
    let mainComponentPixels: Int
    let alphaPixelsBefore: Int
    let alphaPixelsAfter: Int
}

func writePNG(_ image: CGImage, to url: URL) throws {
    guard let destination = CGImageDestinationCreateWithURL(
        url as CFURL, UTType.png.identifier as CFString, 1, nil
    ) else {
        throw NSError(domain: "SegmentPresenter", code: 3, userInfo: [NSLocalizedDescriptionKey: "Cannot create PNG: \(url.path)"])
    }
    CGImageDestinationAddImage(destination, image, nil)
    guard CGImageDestinationFinalize(destination) else {
        throw NSError(domain: "SegmentPresenter", code: 4, userInfo: [NSLocalizedDescriptionKey: "Cannot finish PNG: \(url.path)"])
    }
}

func grayCGImage(_ pixels: [UInt8], width: Int, height: Int) throws -> CGImage {
    let data = Data(pixels)
    guard let provider = CGDataProvider(data: data as CFData),
          let image = CGImage(
            width: width,
            height: height,
            bitsPerComponent: 8,
            bitsPerPixel: 8,
            bytesPerRow: width,
            space: CGColorSpaceCreateDeviceGray(),
            bitmapInfo: CGBitmapInfo(rawValue: CGImageAlphaInfo.none.rawValue),
            provider: provider,
            decode: nil,
            shouldInterpolate: true,
            intent: .defaultIntent
          ) else {
        throw NSError(domain: "SegmentPresenter", code: 5, userInfo: [NSLocalizedDescriptionKey: "Cannot create grayscale mask image"])
    }
    return image
}

func resampleMask(_ source: CVPixelBuffer, width: Int, height: Int) throws -> [UInt8] {
    let sourceWidth = CVPixelBufferGetWidth(source)
    let sourceHeight = CVPixelBufferGetHeight(source)
    let sourceRowBytes = CVPixelBufferGetBytesPerRow(source)
    guard CVPixelBufferLockBaseAddress(source, .readOnly) == kCVReturnSuccess,
          let sourceBase = CVPixelBufferGetBaseAddress(source) else {
        throw NSError(domain: "SegmentPresenter", code: 6, userInfo: [NSLocalizedDescriptionKey: "Cannot lock Vision mask"])
    }
    defer { CVPixelBufferUnlockBaseAddress(source, .readOnly) }
    let sourceBytes = sourceBase.assumingMemoryBound(to: UInt8.self)
    var result = Array(repeating: UInt8(0), count: width * height)

    for y in 0..<height {
        let sourceY = (Double(y) + 0.5) * Double(sourceHeight) / Double(height) - 0.5
        let y0 = max(0, min(sourceHeight - 1, Int(floor(sourceY))))
        let y1 = min(sourceHeight - 1, y0 + 1)
        let fy = max(0.0, min(1.0, sourceY - Double(y0)))
        for x in 0..<width {
            let sourceX = (Double(x) + 0.5) * Double(sourceWidth) / Double(width) - 0.5
            let x0 = max(0, min(sourceWidth - 1, Int(floor(sourceX))))
            let x1 = min(sourceWidth - 1, x0 + 1)
            let fx = max(0.0, min(1.0, sourceX - Double(x0)))
            let top = Double(sourceBytes[y0 * sourceRowBytes + x0]) * (1 - fx)
                + Double(sourceBytes[y0 * sourceRowBytes + x1]) * fx
            let bottom = Double(sourceBytes[y1 * sourceRowBytes + x0]) * (1 - fx)
                + Double(sourceBytes[y1 * sourceRowBytes + x1]) * fx
            result[y * width + x] = UInt8(max(0, min(255, Int((top * (1 - fy) + bottom * fy).rounded()))))
        }
    }
    return result
}

func dilate(_ source: [UInt8], width: Int, height: Int, radius: Int) -> [UInt8] {
    guard radius > 0 else { return source }
    var horizontal = Array(repeating: UInt8(0), count: source.count)
    var result = horizontal
    for y in 0..<height {
        let row = y * width
        for x in 0..<width {
            var maximum: UInt8 = 0
            let start = max(0, x - radius)
            let end = min(width - 1, x + radius)
            for position in start...end { maximum = max(maximum, source[row + position]) }
            horizontal[row + x] = maximum
        }
    }
    for y in 0..<height {
        let start = max(0, y - radius)
        let end = min(height - 1, y + radius)
        for x in 0..<width {
            var maximum: UInt8 = 0
            for position in start...end { maximum = max(maximum, horizontal[position * width + x]) }
            result[y * width + x] = maximum
        }
    }
    return result
}

func cleanupMask(_ source: [UInt8], width: Int, height: Int, threshold: UInt8, radius: Int) -> CleanupResult {
    let count = width * height
    var visited = Array(repeating: false, count: count)
    var queue = [Int]()
    var componentSizes = [Int]()
    var largest = [Int]()

    for start in 0..<count where !visited[start] && source[start] >= threshold {
        queue.removeAll(keepingCapacity: true)
        queue.append(start)
        visited[start] = true
        var cursor = 0
        while cursor < queue.count {
            let point = queue[cursor]
            cursor += 1
            let x = point % width
            let y = point / width
            let yStart = max(0, y - 1)
            let yEnd = min(height - 1, y + 1)
            let xStart = max(0, x - 1)
            let xEnd = min(width - 1, x + 1)
            for neighborY in yStart...yEnd {
                for neighborX in xStart...xEnd {
                    let neighbor = neighborY * width + neighborX
                    if !visited[neighbor] && source[neighbor] >= threshold {
                        visited[neighbor] = true
                        queue.append(neighbor)
                    }
                }
            }
        }
        componentSizes.append(queue.count)
        if queue.count > largest.count { largest = queue }
    }

    let alphaBefore = source.reduce(into: 0) { if $1 > 0 { $0 += 1 } }
    var mainBinary = Array(repeating: UInt8(0), count: count)
    for pixel in largest { mainBinary[pixel] = 255 }
    let allowed = dilate(mainBinary, width: width, height: height, radius: radius)
    var output = source
    var alphaAfter = 0
    for index in 0..<count {
        if allowed[index] == 0 { output[index] = 0 }
        if output[index] > 0 { alphaAfter += 1 }
    }
    return CleanupResult(
        pixels: output,
        componentCount: componentSizes.count,
        mainComponentPixels: largest.count,
        alphaPixelsBefore: alphaBefore,
        alphaPixelsAfter: alphaAfter
    )
}

func makeMaskBuffer(_ pixels: [UInt8], width: Int, height: Int) throws -> CVPixelBuffer {
    var buffer: CVPixelBuffer?
    let attributes: [String: Any] = [
        kCVPixelBufferIOSurfacePropertiesKey as String: [:],
        kCVPixelBufferMetalCompatibilityKey as String: true
    ]
    let status = CVPixelBufferCreate(
        kCFAllocatorDefault,
        width,
        height,
        kCVPixelFormatType_OneComponent8,
        attributes as CFDictionary,
        &buffer
    )
    guard status == kCVReturnSuccess, let buffer,
          CVPixelBufferLockBaseAddress(buffer, []) == kCVReturnSuccess,
          let base = CVPixelBufferGetBaseAddress(buffer) else {
        throw NSError(domain: "SegmentPresenter", code: 7, userInfo: [NSLocalizedDescriptionKey: "Cannot allocate output mask buffer"])
    }
    defer { CVPixelBufferUnlockBaseAddress(buffer, []) }
    let rowBytes = CVPixelBufferGetBytesPerRow(buffer)
    let destination = base.assumingMemoryBound(to: UInt8.self)
    for y in 0..<height {
        pixels.withUnsafeBufferPointer { source in
            destination.advanced(by: y * rowBytes).update(from: source.baseAddress!.advanced(by: y * width), count: width)
        }
    }
    return buffer
}

func makePreview(source: CVPixelBuffer, maskPixels: [UInt8], width: Int, height: Int, context: CIContext) throws -> CGImage {
    let maskBuffer = try makeMaskBuffer(maskPixels, width: width, height: height)
    let sourceImage = CIImage(cvPixelBuffer: source)
    let extent = sourceImage.extent
    let maskImage = CIImage(cvPixelBuffer: maskBuffer)
    let background = CIImage(color: CIColor(red: 0.05, green: 1.0, blue: 0.45, alpha: 1.0)).cropped(to: extent)
    let result = sourceImage.applyingFilter("CIBlendWithMask", parameters: [
        kCIInputBackgroundImageKey: background,
        kCIInputMaskImageKey: maskImage
    ])
    guard let image = context.createCGImage(result, from: extent) else {
        throw NSError(domain: "SegmentPresenter", code: 8, userInfo: [NSLocalizedDescriptionKey: "Cannot create mask preview"])
    }
    return image
}

func requireEmptyOutputDirectory(_ url: URL) throws {
    let fileManager = FileManager.default
    var isDirectory = ObjCBool(false)
    if fileManager.fileExists(atPath: url.path, isDirectory: &isDirectory) {
        guard isDirectory.boolValue else {
            throw NSError(domain: "SegmentPresenter", code: 15, userInfo: [
                NSLocalizedDescriptionKey: "Output path exists and is not a directory: \(url.path)"
            ])
        }
        let entries = try fileManager.contentsOfDirectory(atPath: url.path)
        guard entries.isEmpty else {
            throw NSError(domain: "SegmentPresenter", code: 16, userInfo: [
                NSLocalizedDescriptionKey: "Output directory must be empty to preserve existing proof artifacts: \(url.path)"
            ])
        }
    } else {
        try fileManager.createDirectory(at: url, withIntermediateDirectories: true)
    }
}

func isIdentityTransform(_ transform: CGAffineTransform, tolerance: CGFloat = 0.000001) -> Bool {
    abs(transform.a - 1) <= tolerance
        && abs(transform.b) <= tolerance
        && abs(transform.c) <= tolerance
        && abs(transform.d - 1) <= tolerance
        && abs(transform.tx) <= tolerance
        && abs(transform.ty) <= tolerance
}

func run(_ options: Options) async throws {
    let asset = AVURLAsset(url: options.input)
    guard let track = try await asset.loadTracks(withMediaType: .video).first else {
        throw NSError(domain: "SegmentPresenter", code: 9, userInfo: [NSLocalizedDescriptionKey: "Input has no video track"])
    }
    let preferredTransform = try await track.load(.preferredTransform)
    guard isIdentityTransform(preferredTransform) else {
        throw NSError(domain: "SegmentPresenter", code: 17, userInfo: [
            NSLocalizedDescriptionKey: "Input has a nonidentity preferredTransform; rotate/export it upright before segmentation."
        ])
    }
    let duration = CMTimeGetSeconds(try await asset.load(.duration))
    let nominalRate = Double(try await track.load(.nominalFrameRate))
    let reader = try AVAssetReader(asset: asset)
    let trackOutput = AVAssetReaderTrackOutput(track: track, outputSettings: [
        kCVPixelBufferPixelFormatTypeKey as String: kCVPixelFormatType_32BGRA
    ])
    trackOutput.alwaysCopiesSampleData = false
    guard reader.canAdd(trackOutput) else {
        throw NSError(domain: "SegmentPresenter", code: 10, userInfo: [NSLocalizedDescriptionKey: "Cannot attach video reader"])
    }
    reader.add(trackOutput)
    guard reader.startReading() else {
        throw reader.error ?? NSError(domain: "SegmentPresenter", code: 11, userInfo: [NSLocalizedDescriptionKey: "Cannot start video reader"])
    }

    let context = CIContext(options: [.useSoftwareRenderer: false])
    let sequenceHandler = VNSequenceRequestHandler()
    let reusableRequest: VNGeneratePersonSegmentationRequest? = options.sampleTimes == nil
        ? VNGeneratePersonSegmentationRequest()
        : nil
    reusableRequest?.qualityLevel = .accurate
    reusableRequest?.outputPixelFormat = kCVPixelFormatType_OneComponent8
    var nextSampleIndex = 0
    var sampleResults = [FrameEvidence]()
    var decodedIndex = 0
    var outputWidth = 0
    var outputHeight = 0

    while let sampleBuffer = trackOutput.copyNextSampleBuffer() {
        guard let frame = CMSampleBufferGetImageBuffer(sampleBuffer) else { continue }
        let time = CMTimeGetSeconds(CMSampleBufferGetPresentationTimeStamp(sampleBuffer))
        outputWidth = CVPixelBufferGetWidth(frame)
        outputHeight = CVPixelBufferGetHeight(frame)

        let shouldProcess: Bool
        if let sampleTimes = options.sampleTimes {
            shouldProcess = nextSampleIndex < sampleTimes.count && time + 0.0001 >= sampleTimes[nextSampleIndex]
        } else {
            shouldProcess = true
        }
        if shouldProcess {
            let request = reusableRequest ?? VNGeneratePersonSegmentationRequest()
            request.qualityLevel = .accurate
            request.outputPixelFormat = kCVPixelFormatType_OneComponent8
            let start = DispatchTime.now().uptimeNanoseconds
            try sequenceHandler.perform([request], on: frame, orientation: .up)
            let elapsed = Double(DispatchTime.now().uptimeNanoseconds - start) / 1_000_000
            guard let observation = request.results?.first else {
                throw NSError(domain: "SegmentPresenter", code: 12, userInfo: [NSLocalizedDescriptionKey: "Vision returned no mask at \(time)s"])
            }
            let originalMask = try resampleMask(observation.pixelBuffer, width: outputWidth, height: outputHeight)
            let cleaned = cleanupMask(
                originalMask,
                width: outputWidth,
                height: outputHeight,
                threshold: options.componentThreshold,
                radius: options.softRadius
            )

            let outputStem: String
            if options.sampleTimes != nil {
                outputStem = String(format: "sample-%03d-%05.2fs", nextSampleIndex, time)
            } else {
                outputStem = String(format: "frame-%06d", decodedIndex)
            }
            let maskName = "\(outputStem)-mask.png"
            let maskURL = options.outputDirectory.appendingPathComponent(maskName)
            try writePNG(grayCGImage(cleaned.pixels, width: outputWidth, height: outputHeight), to: maskURL)
            let previewName: String?
            if options.writePreviews {
                let name = "\(outputStem)-preview.png"
                try writePNG(
                    makePreview(source: frame, maskPixels: cleaned.pixels, width: outputWidth, height: outputHeight, context: context),
                    to: options.outputDirectory.appendingPathComponent(name)
                )
                previewName = name
            } else {
                previewName = nil
            }
            sampleResults.append(FrameEvidence(
                frameIndex: decodedIndex,
                presentationTimeSeconds: time,
                inferenceMilliseconds: elapsed,
                maskWidth: outputWidth,
                maskHeight: outputHeight,
                componentsAtThreshold: cleaned.componentCount,
                mainComponentPixels: cleaned.mainComponentPixels,
                alphaPixelsBefore: cleaned.alphaPixelsBefore,
                alphaPixelsAfter: cleaned.alphaPixelsAfter,
                removedAlphaPixels: cleaned.alphaPixelsBefore - cleaned.alphaPixelsAfter,
                maskPath: maskName,
                previewPath: previewName
            ))
            if let sampleTimes = options.sampleTimes { nextSampleIndex += 1; _ = sampleTimes }
        }
        decodedIndex += 1
    }
    guard reader.status == .completed else {
        throw reader.error ?? NSError(domain: "SegmentPresenter", code: 13, userInfo: [NSLocalizedDescriptionKey: "Video reader ended with status \(reader.status.rawValue)"])
    }
    if let sampleTimes = options.sampleTimes, nextSampleIndex != sampleTimes.count {
        throw NSError(domain: "SegmentPresenter", code: 14, userInfo: [NSLocalizedDescriptionKey: "Only reached \(nextSampleIndex) of \(sampleTimes.count) sample times"])
    }

    var usage = rusage()
    guard getrusage(RUSAGE_SELF, &usage) == 0 else {
        throw NSError(domain: NSPOSIXErrorDomain, code: Int(errno), userInfo: nil)
    }
    let evidence = RunEvidence(
        inputPath: options.input.path,
        inputDurationSeconds: duration,
        inputWidth: outputWidth,
        inputHeight: outputHeight,
        inputFrameRate: nominalRate,
        mode: options.sampleTimes == nil ? "all-frames" : "sample-times",
        sampleTimesSeconds: options.sampleTimes ?? [],
        componentThreshold: options.componentThreshold,
        softRadiusPixels: options.softRadius,
        visionRequest: "VNGeneratePersonSegmentationRequest qualityLevel=accurate",
        visionOutputPixelFormat: "kCVPixelFormatType_OneComponent8",
        frameCount: decodedIndex,
        maximumResidentSetBytes: usage.ru_maxrss,
        frames: sampleResults
    )
    let encoder = JSONEncoder()
    encoder.outputFormatting = [.prettyPrinted, .sortedKeys]
    try encoder.encode(evidence).write(to: options.outputDirectory.appendingPathComponent("report.json"), options: .atomic)
    print(String(data: try encoder.encode(evidence), encoding: .utf8) ?? "{}")
}

@main
struct SegmentPresenter {
    static func main() async {
        if CommandLine.arguments.contains("--help") {
            print("Usage: segment-presenter --input source.mp4 --output-dir OUTPUT (--sample-times 2,6,... | --all-frames true) [--component-threshold 32] [--soft-radius 6] [--no-previews]")
            return
        }
        do {
            let options = try Options.parse(CommandLine.arguments)
            try requireEmptyOutputDirectory(options.outputDirectory)
            try await run(options)
        } catch {
            let message = "segment-presenter: \(error.localizedDescription)\n"
            FileHandle.standardError.write(Data(message.utf8))
            exit(2)
        }
    }
}
