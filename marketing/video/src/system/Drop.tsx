/**
 * THE ONE MASTER.
 *
 * A single component that turns a DropConfig into a video. There is no per-drop
 * component, no per-aspect component, and no per-aspect timeline: this file is
 * mounted three times at three frame sizes and re-lays-out each time.
 *
 * Adding a campaign video is adding a config object to src/drops/index.ts.
 */
import React from 'react';
import { AbsoluteFill, Audio, Sequence, staticFile } from 'remotion';
import { Chrome, Grain, Ground, KitProvider } from './kit';
import { sceneOpacity } from './motion';
import {
  ChecklistScene,
  OfferCardScene,
  PlateScene,
  SplitScene,
  StatScene,
  StatementScene,
  OutroScene,
} from './scenes';
import type { DropConfig, Scene } from './types';

export const DEFAULT_FPS = 30;

/** Frame length of one scene. */
export const sceneFrames = (s: Scene, fps: number) => Math.round(s.seconds * fps);

/** Total frames of a drop — derived, never hand-maintained. */
export const dropFrames = (d: DropConfig): number => {
  const fps = d.fps ?? DEFAULT_FPS;
  return d.scenes.reduce((n, s) => n + sceneFrames(s, fps), 0);
};

const SceneBody: React.FC<{ scene: Scene; length: number; ctaUrl: string }> = ({ scene, length, ctaUrl }) => {
  switch (scene.kind) {
    case 'plate':
      return <PlateScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'split':
      return <SplitScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'stat':
      return <StatScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'offer-card':
      return <OfferCardScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'checklist':
      return <ChecklistScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'statement':
      return <StatementScene scene={scene} length={length} ctaUrl={ctaUrl} />;
    case 'outro':
      return <OutroScene scene={scene} length={length} ctaUrl={ctaUrl} />;
  }
};

/** Wraps every scene in the one transition the system has: a fade up. */
const SceneEnvelope: React.FC<{ scene: Scene; length: number; ctaUrl: string; last: boolean }> = ({
  scene,
  length,
  ctaUrl,
  last,
}) => {
  const Inner: React.FC = () => {
    // sceneOpacity is a hook-free frame function; read it inside the Sequence so
    // the frame is scene-local, which is what makes scene timing composable.
    return <SceneBody scene={scene} length={length} ctaUrl={ctaUrl} />;
  };
  return (
    <FadeUp length={length} last={last}>
      <Inner />
    </FadeUp>
  );
};

const FadeUp: React.FC<{ length: number; last: boolean; children: React.ReactNode }> = ({
  length,
  last,
  children,
}) => {
  const frame = useSceneFrame();
  return <AbsoluteFill style={{ opacity: sceneOpacity(frame, length, last) }}>{children}</AbsoluteFill>;
};

// Sequence-local frame. Imported here rather than at the top so the dependency
// on Remotion's frame context is obvious at the point of use.
import { useCurrentFrame } from 'remotion';
const useSceneFrame = () => useCurrentFrame();

export const Drop: React.FC<{ config: DropConfig }> = ({ config }) => {
  const fps = config.fps ?? DEFAULT_FPS;
  const total = dropFrames(config);
  let cursor = 0;

  return (
    <KitProvider palette={config.palette}>
      <AbsoluteFill>
        <Ground />
        {config.scenes.map((scene, i) => {
          const length = sceneFrames(scene, fps);
          const from = cursor;
          cursor += length;
          return (
            <Sequence key={`${scene.kind}-${i}`} from={from} durationInFrames={length}>
              <SceneEnvelope
                scene={scene}
                length={length}
                ctaUrl={config.ctaUrl}
                last={i === config.scenes.length - 1}
              />
            </Sequence>
          );
        })}
        <Chrome totalFrames={total} />
        <Grain />
        {config.audio ? <Audio src={staticFile(config.audio)} /> : null}
      </AbsoluteFill>
    </KitProvider>
  );
};
