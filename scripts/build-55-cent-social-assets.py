#!/usr/bin/env python3
"""Build platform-ready 55 Cents a Day campaign graphics from approved art."""

from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageEnhance, ImageFilter
import textwrap

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "frontend/public/blog-images"
OUT = ROOT / "marketing/55-cent-campaign/assets"
OUT.mkdir(parents=True, exist_ok=True)

LIME = "#B8FF00"
WHITE = "#FFFFFF"
MUTED = "#C8CDC9"
BLACK = "#050706"
FONT_BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
FONT_REG = "/System/Library/Fonts/Supplemental/Arial.ttf"
URL = "FAMTASTICDESIGNS.COM/55-CENTS-A-DAY-WEBSITE"


def font(size, bold=True):
    return ImageFont.truetype(FONT_BOLD if bold else FONT_REG, size)


def cover(image, size, focus=(0.5, 0.5)):
    image = image.convert("RGB")
    ratio = max(size[0] / image.width, size[1] / image.height)
    resized = image.resize((round(image.width * ratio), round(image.height * ratio)), Image.Resampling.LANCZOS)
    left = max(0, round((resized.width - size[0]) * focus[0]))
    top = max(0, round((resized.height - size[1]) * focus[1]))
    return resized.crop((left, top, left + size[0], top + size[1]))


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
    draw.rounded_rectangle((56, y, width - 56, y + int(72 * scale)), radius=18, fill=(5, 7, 6, 220), outline=LIME, width=2)
    label_font = font(int(28 * scale))
    x = 82
    draw.text((x, y + int(16 * scale)), "FAM", font=label_font, fill=LIME)
    fam_width = draw.textbbox((0, 0), "FAM", font=label_font)[2]
    draw.text((x + fam_width, y + int(16 * scale)), "TASTIC DESIGNS", font=label_font, fill=WHITE)


def render(name, source, size, headline, subhead, focus=(0.5, 0.5), headline_size=78, text_position="bottom"):
    base = cover(Image.open(SRC / source), size, focus)
    base = ImageEnhance.Contrast(base).enhance(1.06)
    shade = Image.new("RGBA", size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(shade)
    if text_position == "bottom":
        for i in range(size[1]):
            alpha = max(0, min(235, int((i / size[1] - .35) * 400)))
            sd.line((0, i, size[0], i), fill=(0, 0, 0, alpha))
        y = int(size[1] * .56)
    else:
        sd.rectangle((0, 0, int(size[0] * .68), size[1]), fill=(0, 0, 0, 205))
        y = int(size[1] * .18)
    base = Image.alpha_composite(base.convert("RGBA"), shade)
    draw = ImageDraw.Draw(base)
    brand(draw, size[0], 44, 1)
    fnt = font(headline_size)
    lines = wrap(draw, headline, fnt, size[0] - 112)
    line_h = int(headline_size * 1.02)
    for idx, line in enumerate(lines):
        color = LIME if any(token in line for token in ["55¢", "COST", "$199", "TRUST", "FIND?"]) else WHITE
        draw.text((56, y + idx * line_h), line, font=fnt, fill=color, stroke_width=2, stroke_fill=BLACK)
    sy = y + len(lines) * line_h + 24
    sf = font(max(30, int(headline_size * .42)), False)
    for idx, line in enumerate(wrap(draw, subhead, sf, size[0] - 112)):
        draw.text((56, sy + idx * int(sf.size * 1.25)), line, font=sf, fill=MUTED, stroke_width=1, stroke_fill=BLACK)
    footer_y = size[1] - 78
    draw.rectangle((0, footer_y, size[0], size[1]), fill=LIME)
    url_fnt = font(max(19, int(size[0] / 38)))
    bbox = draw.textbbox((0, 0), URL, font=url_fnt)
    draw.text(((size[0] - (bbox[2] - bbox[0])) / 2, footer_y + 22), URL, font=url_fnt, fill=BLACK)
    base.convert("RGB").save(OUT / name, quality=92, optimize=True)


render("instagram-feed-1080x1350.jpg", "campaign-55-cent-character.webp", (1080, 1350), "A PROFESSIONAL WEBSITE FOR ABOUT 55¢ A DAY.", "There may be many reasons your business is still offline. Cost is not one of them. Period.", (0.34, .5), 82)
render("facebook-feed-1200x628.jpg", "campaign-trust-gap.webp", (1200, 628), "WHEN CUSTOMERS CHECK, WHAT DO THEY FIND?", "$199 Web Basics gives your business a professional place to be found and verified.", (.52, .45), 58, "left")
render("tiktok-cover-1080x1920.jpg", "campaign-55-cent-character.webp", (1080, 1920), "55¢ A DAY. COST IS NOT ONE OF THEM.", "A one-time $199 professional first website offer.", (.36, .5), 94)
render("stories-reels-1080x1920.jpg", "campaign-excuses.webp", (1080, 1920), "TOO EXPENSIVE? NOT ANYMORE.", "One focused website. First-year basic hosting. A clear domain path.", (.35, .5), 88)

slides = [
    ("01-hook.jpg", "campaign-55-cent-character.webp", "YOUR BUSINESS CAN HAVE A WEBSITE FOR ABOUT 55¢ A DAY.", "The $199 price is paid once. 55¢ is the annualized comparison.", (.34, .5)),
    ("02-excuses.jpg", "campaign-excuses.webp", "TOO EXPENSIVE. I DON'T NEED ONE. BUSINESS IS FINE.", "The concerns are understandable. The cost barrier is removable.", (.35, .5)),
    ("03-trust.jpg", "campaign-trust-gap.webp", "WHEN A CUSTOMER CHECKS, CAN THEY TRUST YOU?", "A useful website gives your business one clear place to explain, prove, and respond.", (.5, .5)),
    ("04-math.jpg", "campaign-55-cent-equation.webp", "$199 ÷ 365 = ABOUT 55¢ A DAY.", "One-time purchase. First-year basic hosting included. Domain path included.", (.5, .5)),
    ("05-cta.jpg", "campaign-55-cent-character.webp", "COST IS NOT ONE OF THEM. PERIOD.", "Start Web Basics—or take the assessment if your business needs more.", (.34, .5)),
]
for filename, source, headline, subhead, focus in slides:
    render(f"instagram-carousel-{filename}", source, (1080, 1350), headline, subhead, focus, 72)
    render(f"video-{filename}", source, (1080, 1920), headline, subhead, focus, 88)

print(f"Built {len(list(OUT.glob('*.jpg')))} social assets in {OUT}")
