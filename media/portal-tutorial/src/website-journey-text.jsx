import React from 'react';
import {Video} from '@remotion/media';
import {
  AbsoluteFill,
  Easing,
  Sequence,
  interpolate,
  spring,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from 'remotion';

const LIME = '#9BFF45';

const StepCard = ({step, label, title, durationInFrames}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const entrance = spring({
    fps,
    frame,
    config: {damping: 18, stiffness: 150, mass: 0.65},
    durationInFrames: 13,
  });
  const exit = interpolate(
    frame,
    [durationInFrames - 9, durationInFrames],
    [1, 0],
    {extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: Easing.in(Easing.cubic)},
  );

  return (
    <AbsoluteFill>
      <div
        style={{
          position: 'absolute',
          top: 42,
          left: 48,
          width: 790,
          height: 126,
          display: 'flex',
          alignItems: 'center',
          gap: 24,
          padding: '18px 28px 18px 20px',
          boxSizing: 'border-box',
          border: '1px solid rgba(155,255,69,.64)',
          borderRadius: 26,
          background: 'linear-gradient(100deg,rgba(4,7,4,.96),rgba(7,11,7,.86))',
          boxShadow: '0 18px 48px rgba(0,0,0,.48), 0 0 30px rgba(155,255,69,.12)',
          opacity: entrance * exit,
          translate: `0px ${interpolate(entrance, [0, 1], [-24, 0])}px`,
          scale: interpolate(entrance, [0, 1], [0.985, 1], {output: 'perceptual-scale'}),
          fontFamily: 'Arial, Helvetica, sans-serif',
        }}
      >
        <div
          style={{
            width: 76,
            height: 76,
            flex: '0 0 76px',
            display: 'grid',
            placeItems: 'center',
            borderRadius: 22,
            background: LIME,
            color: '#071000',
            fontSize: 40,
            fontWeight: 900,
            boxShadow: '0 0 28px rgba(155,255,69,.36)',
            scale: interpolate(frame, [0, 10, 16], [0.76, 1.06, 1], {
              extrapolateLeft: 'clamp',
              extrapolateRight: 'clamp',
              easing: Easing.out(Easing.back(1.7)),
              output: 'perceptual-scale',
            }),
          }}
        >
          {step}
        </div>
        <div style={{minWidth: 0}}>
          <div
            style={{
              marginBottom: 5,
              color: LIME,
              fontSize: 21,
              fontWeight: 900,
              letterSpacing: '0.14em',
              textTransform: 'uppercase',
            }}
          >
            {label}
          </div>
          <div
            style={{
              color: '#FFFFFF',
              fontSize: 49,
              fontWeight: 900,
              letterSpacing: '-0.035em',
              lineHeight: 1,
              whiteSpace: 'nowrap',
            }}
          >
            {title}
          </div>
        </div>
        <div
          style={{
            position: 'absolute',
            left: 124,
            right: 28,
            bottom: 9,
            height: 3,
            borderRadius: 99,
            background: `linear-gradient(90deg,${LIME},${LIME})`,
            transformOrigin: 'left center',
            scale: `${interpolate(frame, [4, durationInFrames - 10], [0, 1], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'})} 1`,
            opacity: 0.72,
          }}
        />
      </div>
    </AbsoluteFill>
  );
};

export const WebsiteJourneyText = () => (
  <AbsoluteFill style={{backgroundColor: '#050705'}}>
    <Video
      src={staticFile('website-journey-clay-v1.mp4')}
      playbackRate={0.75}
      muted
      durationInFrames={289}
      objectFit="cover"
      style={{width: '100%', height: '100%'}}
    />
    <AbsoluteFill
      style={{
        background: 'linear-gradient(180deg,rgba(2,4,2,.24) 0%,transparent 42%,rgba(2,4,2,.08) 100%)',
      }}
    />

    <Sequence from={0} durationInFrames={45} name="Step 1 — Register">
      <StepCard step="1" label="First" title="Register your account" durationInFrames={45} />
    </Sequence>
    <Sequence from={43} durationInFrames={46} name="Step 2 — Start">
      <StepCard step="2" label="Then" title="Tap Start My Website" durationInFrames={46} />
    </Sequence>
    <Sequence from={87} durationInFrames={48} name="Step 3 — Brief">
      <StepCard step="3" label="Quick brief" title="Tell us what you need" durationInFrames={48} />
    </Sequence>
    <Sequence from={133} durationInFrames={47} name="Step 4 — Proofs">
      <StepCard step="4" label="Your options" title="View 3 custom proofs" durationInFrames={47} />
    </Sequence>
    <Sequence from={178} durationInFrames={43} name="Step 5 — Select">
      <StepCard step="5" label="Your choice" title="Choose your favorite" durationInFrames={43} />
    </Sequence>
    <Sequence from={219} durationInFrames={38} name="Step 6 — Pay">
      <StepCard step="6" label="Secure checkout" title="Pay when you’re ready" durationInFrames={38} />
    </Sequence>
    <Sequence from={255} durationInFrames={34} name="Step 7 — Done">
      <StepCard step="✓" label="That’s it" title="We build it. You track it." durationInFrames={34} />
    </Sequence>
  </AbsoluteFill>
);
