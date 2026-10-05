import Foundation
import Vision
import AppKit

// Local QR decode, no network. Uses built-in macOS Vision rather than new packages.
guard CommandLine.arguments.count == 2,
      let source = NSImage(contentsOfFile: CommandLine.arguments[1]),
      let image = source.cgImage(forProposedRect: nil, context: nil, hints: nil) else {
    fputs("QR image could not be read\n", stderr); exit(2)
}
let request = VNDetectBarcodesRequest()
request.symbologies = [.qr]
request.usesCPUOnly = true
do {
    try VNImageRequestHandler(cgImage: image, options: [:]).perform([request])
    let payloads = (request.results ?? []).compactMap { $0.payloadStringValue }
    guard payloads.count == 1, payloads[0] == "https://famtasticdesigns.com/connect" else {
        fputs("QR decode mismatch\n", stderr); exit(1)
    }
    print(payloads[0])
} catch {
    fputs("QR decode failed: \(error)\n", stderr); exit(1)
}
