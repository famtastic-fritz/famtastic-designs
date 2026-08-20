import React from 'react';
import {Composition} from 'remotion';
import {WebsiteJourneyText} from './website-journey-text.jsx';

export const PortalTutorialRoot = () => (
  <Composition
    id="WebsiteJourneyText"
    component={WebsiteJourneyText}
    durationInFrames={289}
    fps={24}
    width={1024}
    height={510}
  />
);
