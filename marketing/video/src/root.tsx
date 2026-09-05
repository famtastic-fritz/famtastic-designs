import React from 'react';
import {Composition} from 'remotion';
import {FiftyFiveCentsPortrait} from './scenes/FiftyFiveCentsPortrait';
import {Drop06} from './drop06/Drop06';
import {FPS, TOTAL_FRAMES} from './drop06/script';
import {Drop, dropFrames} from './system/Drop';
import {platformDependency} from './drops/platform-dependency';

const PlatformDependency: React.FC = () => <Drop config={platformDependency} />;

export const FamtasticVideoRoot: React.FC = () => (
  <>
    <Composition
      id="Famtastic55CentsPortrait"
      component={FiftyFiveCentsPortrait}
      durationInFrames={450}
      fps={30}
      width={1080}
      height={1920}
    />
    <Composition
      id="Drop06GmailLinktree"
      component={Drop06}
      durationInFrames={TOTAL_FRAMES}
      fps={FPS}
      width={1080}
      height={1920}
    />
    <Composition
      id="PlatformDependency-9x16"
      component={PlatformDependency}
      durationInFrames={dropFrames(platformDependency)}
      fps={30}
      width={1080}
      height={1920}
    />
    <Composition
      id="PlatformDependency-1x1"
      component={PlatformDependency}
      durationInFrames={dropFrames(platformDependency)}
      fps={30}
      width={1080}
      height={1080}
    />
    <Composition
      id="PlatformDependency-16x9"
      component={PlatformDependency}
      durationInFrames={dropFrames(platformDependency)}
      fps={30}
      width={1920}
      height={1080}
    />
  </>
);
