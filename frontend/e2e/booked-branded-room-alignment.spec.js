import { expect, test } from '@playwright/test';

const rooms = [
  'coastline-crown-barbers',
  'velvet-coil-atelier',
  'palmera-fade-society',
  'saltline-color-house',
];

for (const room of rooms) {
  test(`${room} keeps all three desktop direction cards aligned`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1100 });
    await page.goto(`/showcase/booked-and-branded-pilot/rooms/${room}/`);

    const cards = page.locator('.direction-card');
    await expect(cards).toHaveCount(3);

    const boxes = await cards.evaluateAll((items) => items.map((item) => {
      const box = item.getBoundingClientRect();
      return { top: box.top, bottom: box.bottom };
    }));

    const topSpread = Math.max(...boxes.map((box) => box.top)) - Math.min(...boxes.map((box) => box.top));
    const bottomSpread = Math.max(...boxes.map((box) => box.bottom)) - Math.min(...boxes.map((box) => box.bottom));

    expect(topSpread).toBeLessThanOrEqual(1);
    expect(bottomSpread).toBeLessThanOrEqual(1);
  });
}
