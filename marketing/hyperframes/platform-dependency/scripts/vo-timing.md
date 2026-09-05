# Narration timing — measured, not estimated

Source: `marketing/creative/heygen/renders/take-a-platform-dependency.mp4`
(18.455s, AAC 48 kHz stereo). Script:
`marketing/creative/heygen/scripts/take-a-platform-dependency.json`.

## Method

`silencedetect` found nothing at −32 dB, −25 dB, −22 dB or −18 dB — the take carries
continuous room tone, so a dB gate never opens. A relative RMS envelope was used
instead: mono 16 kHz PCM, 25 ms windows, gaps = runs below 15 % of peak RMS lasting
≥ 0.12 s.

```bash
ffmpeg -v error -i marketing/creative/heygen/renders/take-a-platform-dependency.mp4 \
  -ac 1 -ar 16000 -f s16le /tmp/vo.raw
# then the 25 ms RMS scan (see the README's "How the cut was timed" section)
```

## Result

Seven sentences, seven long gaps. `vo` is time inside the take; `comp` is time in
this composition, where the narration is placed at `data-start="0.6"`.

| # | sentence | vo start | vo end | comp start | comp end |
|---|---|---|---|---|---|
| 1 | Your business is real. | 0.25 | 1.25 | 0.85 | 1.85 |
| 2 | Your customers are real. | 1.58 | 2.78 | 2.18 | 3.38 |
| 3 | But the place they find you, you do not own it. | 3.10 | 5.35 | 3.70 | 5.95 |
| 4 | A profile is an address inside somebody else's building. | 5.75 | 8.80 | 6.35 | 9.40 |
| 5 | The rules can change, and nobody asks you first. | 9.15 | 11.58 | 9.75 | 12.18 |
| 6 | A site of your own works differently. | 12.03 | 13.50 | 12.63 | 14.10 |
| 7 | It sits at a domain you own, and it answers the question at two in the morning, without you. | 13.95 | 18.30 | 14.55 | 18.90 |

Narration ends at comp 19.05.

## Cuts

Every scene boundary sits **inside** one of those gaps, so no cut lands mid-sentence:

| boundary | comp time | gap it sits in |
|---|---|---|
| hook → friction | 3.55 | 3.38 – 3.70 |
| friction → mechanism | 6.15 | 5.95 – 6.35 |
| mechanism → turn | 12.55 | 12.18 – 12.63 |
| turn → offer | 19.60 | after narration (19.05) |
| offer → close | 25.00 | silence |

## Presenter inset lip-sync

The inset is a **muted** `<video>` of the same file; sound comes from a separate
`<audio>` pointed at the same source, per the framework's media contract. The inset
opens at comp 14.90, so it must show source frame 14.90 − 0.60 = **14.30**:
`data-media-start="14.3"`. Any drift here would read instantly as bad dubbing.
