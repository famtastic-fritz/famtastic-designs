#!/usr/bin/env python3
"""Generate favicon and OG image variants from the brand logo."""
from PIL import Image
import os

BASE_DIR = "sites/site-famtastic-designs/public"
SOURCE = os.path.join(BASE_DIR, "assets/brand/logo-primary.png")

def ensure_dir(path):
    os.makedirs(os.path.dirname(path), exist_ok=True)

def make_favicon():
    """Create favicon.png (512x512)"""
    src = Image.open(SOURCE).convert("RGBA")
    # Scale to fit 512x512 with padding
    src.thumbnail((480, 480), Image.LANCZOS)
    canvas = Image.new("RGBA", (512, 512), (0, 0, 0, 0))
    x = (512 - src.width) // 2
    y = (512 - src.height) // 2
    canvas.paste(src, (x, y), src)
    out = os.path.join(BASE_DIR, "favicon.png")
    canvas.save(out, "PNG")
    print(f"✅ Created {out} ({canvas.size[0]}x{canvas.size[1]})")

def make_apple_touch_icon():
    """Create apple-touch-icon.png (180x180)"""
    src = Image.open(SOURCE).convert("RGBA")
    src.thumbnail((160, 160), Image.LANCZOS)
    canvas = Image.new("RGBA", (180, 180), (10, 10, 10, 255))
    x = (180 - src.width) // 2
    y = (180 - src.height) // 2
    canvas.paste(src, (x, y), src)
    out = os.path.join(BASE_DIR, "apple-touch-icon.png")
    canvas.save(out, "PNG")
    print(f"✅ Created {out} ({canvas.size[0]}x{canvas.size[1]})")

def make_favicon_ico():
    """Create multi-size favicon.ico (16, 32, 64, 128, 256)"""
    src = Image.open(SOURCE).convert("RGBA")
    sizes = [256, 128, 64, 32, 16]
    icons = []
    for size in sizes:
        icon = src.copy()
        icon.thumbnail((size, size), Image.LANCZOS)
        canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        x = (size - icon.width) // 2
        y = (size - icon.height) // 2
        canvas.paste(icon, (x, y), icon)
        icons.append(canvas)
    out = os.path.join(BASE_DIR, "favicon.ico")
    icons[0].save(out, format="ICO", sizes=[(s, s) for s in sizes])
    print(f"✅ Created {out} with sizes {sizes}")

def make_og_image():
    """Create OG image (1200x630) with dark background and logo centered"""
    width, height = 1200, 630
    bg_color = (10, 10, 10)
    
    canvas = Image.new("RGB", (width, height), bg_color)
    
    # Load and scale logo
    src = Image.open(SOURCE).convert("RGBA")
    src.thumbnail((700, 350), Image.LANCZOS)
    
    x = (width - src.width) // 2
    y = (height - src.height) // 2 - 40
    canvas.paste(src, (x, y), src)
    
    out = os.path.join(BASE_DIR, "og-image.png")
    canvas.save(out, "PNG")
    print(f"✅ Created {out} ({canvas.size[0]}x{canvas.size[1]})")

if __name__ == "__main__":
    make_favicon()
    make_apple_touch_icon()
    make_favicon_ico()
    make_og_image()
    print("\n🎉 All brand assets generated successfully!")
