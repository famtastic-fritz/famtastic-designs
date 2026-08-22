import React from 'react';
import {Composition} from 'remotion';
import {FiftyFiveCentsPortrait} from './scenes/FiftyFiveCentsPortrait';
import {FamtasticProofFlowPortrait} from './scenes/FamtasticProofFlowPortrait';

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
      id="FamtasticProofFlowPortrait"
      component={FamtasticProofFlowPortrait}
      durationInFrames={960}
      fps={30}
      width={1080}
      height={1920}
    />
  </>
);
