#!/usr/bin/env python3
"""Build platform-ready 55 Cents a Day campaign graphics from approved art."""

from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ImageFilter
import textwrap

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "frontend/public/blog-images"
PHOTO_SRC = ROOT / "marketing/55-cent-campaign/source"
OUT = ROOT / "marketing/55-cent-campaign/assets"
OUT.mkdir(parents=True, exist_ok=True)

LIME = "#B8FF00"
WHITE = "#FFFFFF"
MUTED = "#C8CDC9"
BLACK = "#050706"
FONT_BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
FONT_REG = "/System/Library/Fonts/Supplemental/Arial.ttf"
CTA = "START AT FAMTASTICDESIGNS.COM"


def font(size, bold=True):
    return ImageFont.truetype(FONT_BOLD if bold else FONT_REG, size)


def cover(image, size, focus=(0.5, 0.5)):
    image = image.convert("RGB")
    ratio = max(size[0] / image.width, size[1] / image.height)
    resized = image.resize((round(image.width * ratio), round(image.height * ratio)), Image.Resampling.LANCZOS)
    left = max(0, round((resized.width - size[0]) * focus[0]))
    top = max(0, round((resized.height - size[1]) * focus[1]))
    return resized.crop((left, top, left + size[0], top + size[1]))


def source_image(source):
    """Load approved campaign art or a project-owned photoreal source."""
    path = PHOTO_SRC / source if source.startswith("photoreal-") else SRC / source
    return Image.open(path)


def safe_background(image, size, focus=(0.5, 0.5), preserve=False):
    """Build a full canvas without sacrificing source edges to aspect-ratio crops."""
    if not preserve:
        return cover(image, size, focus)
    backdrop = cover(image, size, focus).filter(ImageFilter.GaussianBlur(max(18, size[0] // 32)))
    backdrop = ImageEnhance.Brightness(backdrop).enhance(.46)
    subject = image.convert("RGB")
    subject.thumbnail((int(size[0] * .94), int(size[1] * .82)), Image.Resampling.LANCZOS)
    x = (size[0] - subject.width) // 2
    y = max(int(size[1] * .09), (size[1] - subject.height) // 2)
    backdrop.paste(subject, (x, y))
    return backdrop


def wrap(draw, text, fnt, width):
    words = text.split()
    lines, current = [], ""
    for word in words:
        candidate = f"{current} {word}".strip()
        if draw.textbbox((0, 0), candidate, font=fnt)[2] <= width:
            current = candidate
        else:
            if current: lines.append(current)
            current = word
    if current: lines.append(current)
    return lines


def brand(draw, width, y, scale=1.0):
    inset = max(56, int(width * .052))
    draw.rounded_rectangle((inset, y, width - inset, y + int(72 * scale)), radius=18, fill=BLACK, outline=LIME, width=2)
    label_font = font(int(28 * scale))
    x = 82
    draw.text((x, y + int(16 * scale)), "FAMTASTIC DESIGNS", font=label_font, fill=WHITE)
    draw.rectangle((x, y + int(54 * scale), x + int(66 * scale), y + int(58 * scale)), fill=LIME)


def render(name, source, size, headline, subhead, focus=(0.5, 0.5), headline_size=78,
           text_position="bottom", preserve=False):
    vertical = size[1] / size[0] > 1.4
    base = safe_background(source_image(source), size, focus, preserve)
    base = ImageEnhance.Contrast(base).enhance(1.06)
    shade = Image.new("RGBA", size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(shade)
    if text_position == "bottom":
        for i in range(size[1]):
            alpha = max(0, min(235, int((i / size[1] - .35) * 400)))
            sd.line((0, i, size[0], i), fill=(0, 0, 0, alpha))
        y = int(size[1] * (.51 if vertical else .47))
    else:
        sd.rectangle((0, 0, int(size[0] * .68), size[1]), fill=(0, 0, 0, 205))
        y = int(size[1] * .18)
    base = Image.alpha_composite(base.convert("RGBA"), shade)
    draw = ImageDraw.Draw(base)
    # Keep branding and copy away from Reels/TikTok controls and device crops.
    side = max(56, int(size[0] * .052))
    copy_width = size[0] - side * 2
    max_copy_bottom = int(size[1] * (.78 if vertical else .84))
    while True:
        fnt = font(headline_size)
        lines = wrap(draw, headline, fnt, copy_width)
        subfont_size = max(30, int(headline_size * .42))
        sf = font(subfont_size, False)
        sublines = wrap(draw, subhead, sf, copy_width)
        predicted = y + len(lines) * int(headline_size * 1.02) + 24 + len(sublines) * int(sf.size * 1.25)
        if predicted <= max_copy_bottom or headline_size <= 54:
            break
        headline_size -= 3
    line_h = int(headline_size * 1.02)
    for idx, line in enumerate(lines):
        color = LIME if any(token in line for token in ["55¢", "COST", "$199", "TRUST", "FIND?"]) else WHITE
        draw.text((side, y + idx * line_h), line, font=fnt, fill=color, stroke_width=2, stroke_fill=BLACK)
    sy = y + len(lines) * line_h + 24
    for idx, line in enumerate(sublines):
        draw.text((side, sy + idx * int(sf.size * 1.25)), line, font=sf, fill=MUTED, stroke_width=1, stroke_fill=BLACK)
    footer_h = 82
    footer_y = int(size[1] * .835) if vertical else size[1] - footer_h
    # Render identity and CTA last on a flattened RGB canvas. This avoids partial
    # glyph loss observed in some RGBA-to-optimized-JPEG combinations.
    final = base.convert("RGB")
    final_draw = ImageDraw.Draw(final)
    brand(final_draw, size[0], 64 if vertical else 36, 1)
    final_draw.rectangle((side, footer_y, size[0] - side, footer_y + footer_h), fill=LIME)
    url_fnt = font(max(19, int(size[0] / 38)))
    bbox = final_draw.textbbox((0, 0), CTA, font=url_fnt)
    final_draw.text(((size[0] - (bbox[2] - bbox[0])) / 2, footer_y + 23), CTA, font=url_fnt, fill=BLACK)
    final.save(OUT / name, quality=94, subsampling=0)


render("instagram-feed-1080x1350.jpg", "photoreal-bakery-owner-vertical.png", (1080, 1350), "A PROFESSIONAL WEBSITE FOR ABOUT 55¢ A DAY.", "There may be many reasons your business is still offline. Cost is not one of them. Period.", (.5, .46), 78)
render("facebook-feed-1200x628.jpg", "photoreal-local-owners-wide.png", (1200, 628), "WHEN CUSTOMERS CHECK, WHAT DO THEY FIND?", "$199 Web Basics gives your business a professional place to be found and verified.", (.5, .5), 54, "left")
render("tiktok-cover-1080x1920.jpg", "photoreal-bakery-owner-vertical.png", (1080, 1920), "55¢ A DAY. COST IS NOT ONE OF THEM.", "A one-time $199 professional first website offer.", (.5, .48), 84)
render("stories-reels-1080x1920.jpg", "photoreal-barber-owner-vertical.png", (1080, 1920), "TOO EXPENSIVE? NOT ANYMORE.", "One focused website, eligible domain path, and first-year basic hosting.", (.5, .48), 82)

slides = [
    ("01-hook.jpg", "photoreal-bakery-owner-vertical.png", "YOUR BUSINESS CAN HAVE A WEBSITE FOR ABOUT 55¢ A DAY.", "The $199 price is paid once. 55¢ is the annualized comparison.", (.5, .48)),
    ("02-excuses.jpg", "photoreal-barber-owner-vertical.png", "TOO EXPENSIVE. I DON'T NEED ONE. BUSINESS IS FINE.", "The concerns are understandable. The cost barrier is removable.", (.5, .48)),
    ("03-trust.jpg", "campaign-trust-gap.webp", "WHEN A CUSTOMER CHECKS, CAN THEY TRUST YOU?", "A useful website gives your business one clear place to explain, prove, and respond.", (.5, .5)),
    ("04-math.jpg", "campaign-55-cent-equation.webp", "$199 ÷ 365 = ABOUT 55¢ A DAY.", "One-time purchase. First-year basic hosting included. Eligible new-domain registration or existing-domain connection included.", (.5, .5)),
    ("05-cta.jpg", "photoreal-local-owners-wide.png", "COST IS NOT ONE OF THEM. PERIOD.", "Start Web Basics—or take the assessment if your business needs more.", (.62, .5)),
]
for filename, source, headline, subhead, focus in slides:
    preserve = source.startswith("campaign-")
    render(f"instagram-carousel-{filename}", source, (1080, 1350), headline, subhead, focus, 68, preserve=preserve)
    render(f"video-{filename}", source, (1080, 1920), headline, subhead, focus, 80, preserve=preserve)

print(f"Built {len(list(OUT.glob('*.jpg')))} social assets in {OUT}")
