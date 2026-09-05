import React from 'react';
import { AbsoluteFill, Audio, Sequence, staticFile } from 'remotion';
import { BEATS } from './script';
import { Captions, Chrome, Grain, Ground, OfferBeat, OutroBeat, PlateBeat, PresenterBeat, StatementBeat } from './parts';

/**
 * drop-06 — "Gmail and Linktree", 1080x1920.
 *
 * Tier 1  HeyGen "FAMtastic Guide" presenter (voice-over for the whole piece,
 *         on screen for the open and the close).
 * Tier 2  Gemini Flash Lite cinematic plates, brand-graded at generation time.
 * Tier 3  This Remotion composition — every word, colour and edge is authored
 *         here, so the brand system is deterministic rather than patched on.
 */
export const Drop06: React.FC = () => (
  <AbsoluteFill style={{ backgroundColor: '#070907' }}>
    <Ground />

    {BEATS.map((beat, i) => {
      const length = beat.to - beat.from;
      return (
        <Sequence key={i} from={beat.from} durationInFrames={length}>
          {beat.kind === 'presenter' ? (
            <PresenterBeat eyebrow={beat.eyebrow} trimBefore={beat.trimBefore} />
          ) : beat.kind === 'plate' ? (
            <PlateBeat eyebrow={beat.eyebrow} plate={beat.plate} pan={beat.pan} length={length} />
          ) : beat.kind === 'offer' ? (
            <OfferBeat length={length} />
          ) : beat.kind === 'statement' ? (
            <StatementBeat plate={beat.plate} length={length} />
          ) : (
            <OutroBeat length={length} />
          )}
        </Sequence>
      );
    })}

    <Captions />
    <Chrome />
    <Grain />

    <Audio src={staticFile('drop06/vo.m4a')} />
  </AbsoluteFill>
);
