/**
 * MOTION — deliberately small.
 *
 * The Design DNA allows one glow. The motion equivalent of that restraint is:
 * things fade and things rise, and nothing else. No wipes, no slides, no spins,
 * no cross-dissolve stingers. Everything below is a ramp on opacity, a few
 * pixels of Y, or a very slow scale on a photographic plate.
 *
 * Every helper is frame-deterministic — no springs seeded from wall-clock time,
 * no randomness — so a re-render of the same source produces byte-comparable
 * output and a diff of the source is a diff of the video.
 */
import { interpolate } from 'remotion';

const CLAMP = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;

/** Standard ease-out. Motion decelerates into place; it never overshoots. */
const easeOut = (t: number) => 1 - Math.pow(1 - t, 3);

/** 0 -> 1 over `dur` frames starting at `at`. */
export const fadeIn = (frame: number, at = 0, dur = 10): number =>
  interpolate(frame, [at, at + dur], [0, 1], CLAMP);

/** 1 -> 0 over the last `dur` frames of a scene. */
export const fadeOut = (frame: number, length: number, dur = 10): number =>
  interpolate(frame, [length - dur, length], [1, 0], CLAMP);

/** Eased 0 -> 1, for anything that also moves. */
export const easeIn = (frame: number, at = 0, dur = 14): number =>
  easeOut(interpolate(frame, [at, at + dur], [0, 1], CLAMP));

/** A few pixels of upward settle. `px` is the distance it travels, not a bounce. */
export const rise = (frame: number, at = 0, dur = 16, px = 22): string =>
  `translateY(${(1 - easeIn(frame, at, dur)) * px}px)`;

/** The accent rule that draws itself next to an eyebrow. */
export const wipe = (frame: number, at = 0, dur = 18, to = 84): number =>
  interpolate(frame, [at, at + dur], [0, to], CLAMP) * 1;

/**
 * Ken Burns on a photographic plate. Amplitude is capped at 0.10 of scale
 * across a whole beat — enough that the frame is alive, small enough that it
 * never reads as a zoom effect.
 */
export const kenBurns = (
  frame: number,
  length: number,
  direction: 'in' | 'out' = 'in',
): { scale: number; drift: number } => {
  const a = direction === 'in' ? 1.04 : 1.14;
  const b = direction === 'in' ? 1.14 : 1.04;
  const d = direction === 'in' ? [8, -8] : [-8, 8];
  return {
    scale: interpolate(frame, [0, length], [a, b], { extrapolateRight: 'clamp' }),
    drift: interpolate(frame, [0, length], d as [number, number], { extrapolateRight: 'clamp' }),
  };
};

/**
 * Scene envelope: every scene fades up over 8 frames and, if it is not the last
 * one, holds to its final frame. Scene changes are hard cuts on a fade-up, which
 * is the only transition in the system.
 */
export const sceneOpacity = (frame: number, length: number, tailOut = false): number =>
  tailOut ? Math.min(fadeIn(frame, 0, 8), fadeOut(frame, length, 12)) : fadeIn(frame, 0, 8);

/** Stagger helper: item `i` of a list starts `step` frames after the one before. */
export const stagger = (i: number, at = 12, step = 7): number => at + i * step;
