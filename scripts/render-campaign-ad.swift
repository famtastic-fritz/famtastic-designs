import AppKit
import Foundation

let args = CommandLine.arguments
guard args.count == 8 else {
  fputs("usage: render-campaign-ad.swift input output concept eyebrow headline subhead cta\n", stderr)
  exit(2)
}

let input = args[1]
let output = args[2]
let concept = args[3]
let eyebrow = args[4]
let headline = args[5]
let subhead = args[6]
let cta = args[7]
let canvas = NSSize(width: 1080, height: 1350)

guard let source = NSImage(contentsOfFile: input) else { fatalError("cannot load input") }

func color(_ hex: UInt32, alpha: CGFloat = 1) -> NSColor {
  NSColor(
    red: CGFloat((hex >> 16) & 255) / 255,
    green: CGFloat((hex >> 8) & 255) / 255,
    blue: CGFloat(hex & 255) / 255,
    alpha: alpha
  )
}

func font(_ size: CGFloat, weight: NSFont.Weight) -> NSFont {
  NSFont.systemFont(ofSize: size, weight: weight)
}

func drawText(_ text: String, rect: NSRect, size: CGFloat, weight: NSFont.Weight,
              foreground: NSColor, alignment: NSTextAlignment = .left,
              lineHeight: CGFloat? = nil) {
  let paragraph = NSMutableParagraphStyle()
  paragraph.alignment = alignment
  paragraph.lineBreakMode = .byWordWrapping
  if let lineHeight { paragraph.minimumLineHeight = lineHeight; paragraph.maximumLineHeight = lineHeight }
  let attributes: [NSAttributedString.Key: Any] = [
    .font: font(size, weight: weight),
    .foregroundColor: foreground,
    .paragraphStyle: paragraph,
    .kern: size >= 48 ? -1.5 : 0.4,
  ]
  NSString(string: text).draw(in: rect, withAttributes: attributes)
}

let image = NSImage(size: canvas)
image.lockFocus()
guard let context = NSGraphicsContext.current?.cgContext else { fatalError("no context") }

// Aspect-fill the generated photo.
let sw = source.size.width
let sh = source.size.height
let scale = max(canvas.width / sw, canvas.height / sh)
let dw = sw * scale
let dh = sh * scale
let photoRect = NSRect(x: (canvas.width - dw) / 2, y: (canvas.height - dh) / 2, width: dw, height: dh)
source.draw(in: photoRect, from: .zero, operation: .sourceOver, fraction: 1)

// Branded dark readability field with a transparent falloff.
let gradient = CGGradient(
  colorsSpace: CGColorSpaceCreateDeviceRGB(),
  colors: [color(0x050505, alpha: 0.96).cgColor, color(0x050505, alpha: 0.58).cgColor, color(0x050505, alpha: 0.04).cgColor] as CFArray,
  locations: [0, 0.43, 1]
)!
context.drawLinearGradient(gradient, start: CGPoint(x: 0, y: 1350), end: CGPoint(x: 0, y: 560), options: [])

// Top brand lockup.
let badge = NSBezierPath(roundedRect: NSRect(x: 60, y: 1224, width: 72, height: 72), xRadius: 17, yRadius: 17)
color(0x090909).setFill(); badge.fill()
color(0x73ff00).setStroke(); badge.lineWidth = 2; badge.stroke()
drawText("F", rect: NSRect(x: 60, y: 1229, width: 72, height: 58), size: 46, weight: .black, foreground: color(0x73ff00), alignment: .center)
drawText("FAM", rect: NSRect(x: 150, y: 1261, width: 113, height: 30), size: 24, weight: .black, foreground: color(0x73ff00))
drawText("TASTIC DESIGNS", rect: NSRect(x: 208, y: 1261, width: 310, height: 30), size: 24, weight: .bold, foreground: .white)
drawText(eyebrow.uppercased(), rect: NSRect(x: 62, y: 1181, width: 760, height: 30), size: 19, weight: .bold, foreground: color(0x73ff00))

// Headline and support copy.
drawText(headline, rect: NSRect(x: 58, y: 850, width: 890, height: 310), size: 76, weight: .black, foreground: .white, lineHeight: 78)
drawText(subhead, rect: NSRect(x: 62, y: 700, width: 820, height: 138), size: 29, weight: .semibold, foreground: color(0xf0f0f0), lineHeight: 36)

// CTA and campaign ID.
let button = NSBezierPath(roundedRect: NSRect(x: 60, y: 60, width: 420, height: 76), xRadius: 38, yRadius: 38)
color(0x73ff00).setFill(); button.fill()
drawText(cta, rect: NSRect(x: 82, y: 80, width: 376, height: 36), size: 25, weight: .black, foreground: color(0x050505), alignment: .center)
drawText("famtasticdesigns.com", rect: NSRect(x: 510, y: 82, width: 510, height: 34), size: 23, weight: .bold, foreground: .white, alignment: .right)
drawText(concept, rect: NSRect(x: 62, y: 20, width: 400, height: 24), size: 13, weight: .medium, foreground: color(0xb8b8b8))

image.unlockFocus()

guard let tiff = image.tiffRepresentation,
      let bitmap = NSBitmapImageRep(data: tiff),
      let data = bitmap.representation(using: .png, properties: [:]) else {
  fatalError("cannot encode")
}
try data.write(to: URL(fileURLWithPath: output))
print(output)
