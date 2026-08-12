import AVFoundation
import AppKit
import CoreVideo
import Foundation

let args = CommandLine.arguments
guard args.count >= 4 else {
  fputs("usage: encode-social-slides.swift output.mp4 seconds-per-slide slide1.jpg ...\n", stderr)
  exit(2)
}
let output = URL(fileURLWithPath: args[1])
let seconds = Double(args[2]) ?? 3.0
let slides = args.dropFirst(3).map { URL(fileURLWithPath: $0) }
try? FileManager.default.removeItem(at: output)

let writer = try AVAssetWriter(outputURL: output, fileType: .mp4)
let settings: [String: Any] = [
  AVVideoCodecKey: AVVideoCodecType.h264,
  AVVideoWidthKey: 1080,
  AVVideoHeightKey: 1920,
  AVVideoCompressionPropertiesKey: [
    AVVideoAverageBitRateKey: 8_000_000,
    AVVideoProfileLevelKey: AVVideoProfileLevelH264HighAutoLevel,
  ],
]
let input = AVAssetWriterInput(mediaType: .video, outputSettings: settings)
input.expectsMediaDataInRealTime = false
let adaptor = AVAssetWriterInputPixelBufferAdaptor(assetWriterInput: input, sourcePixelBufferAttributes: [
  kCVPixelBufferPixelFormatTypeKey as String: kCVPixelFormatType_32ARGB,
  kCVPixelBufferWidthKey as String: 1080,
  kCVPixelBufferHeightKey as String: 1920,
])
guard writer.canAdd(input) else { fatalError("cannot add video input") }
writer.add(input)
writer.startWriting()
writer.startSession(atSourceTime: .zero)

func pixelBuffer(_ image: NSImage) -> CVPixelBuffer {
  var buffer: CVPixelBuffer?
  CVPixelBufferCreate(kCFAllocatorDefault, 1080, 1920, kCVPixelFormatType_32ARGB, [
    kCVPixelBufferCGImageCompatibilityKey: true,
    kCVPixelBufferCGBitmapContextCompatibilityKey: true,
  ] as CFDictionary, &buffer)
  let pixel = buffer!
  CVPixelBufferLockBaseAddress(pixel, [])
  let context = CGContext(data: CVPixelBufferGetBaseAddress(pixel), width: 1080, height: 1920, bitsPerComponent: 8, bytesPerRow: CVPixelBufferGetBytesPerRow(pixel), space: CGColorSpaceCreateDeviceRGB(), bitmapInfo: CGImageAlphaInfo.noneSkipFirst.rawValue)!
  context.clear(CGRect(x: 0, y: 0, width: 1080, height: 1920))
  if let cg = image.cgImage(forProposedRect: nil, context: nil, hints: nil) {
    context.draw(cg, in: CGRect(x: 0, y: 0, width: 1080, height: 1920))
  }
  CVPixelBufferUnlockBaseAddress(pixel, [])
  return pixel
}

let fps: Int32 = 30
let framesPerSlide = Int(seconds * Double(fps))
for (slideIndex, url) in slides.enumerated() {
  guard let image = NSImage(contentsOf: url) else { fatalError("cannot load \(url.path)") }
  let pixel = pixelBuffer(image)
  for frame in 0..<framesPerSlide {
    while !input.isReadyForMoreMediaData { Thread.sleep(forTimeInterval: 0.002) }
    let number = slideIndex * framesPerSlide + frame
    adaptor.append(pixel, withPresentationTime: CMTime(value: Int64(number), timescale: fps))
  }
}
input.markAsFinished()
let semaphore = DispatchSemaphore(value: 0)
writer.finishWriting { semaphore.signal() }
semaphore.wait()
guard writer.status == .completed else { fatalError(writer.error?.localizedDescription ?? "video export failed") }
print(output.path)
