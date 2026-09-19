"""Validate a speech performance without rewriting copy; assemble measured gaps."""
from __future__ import annotations

import math


def _number(value, name, low, high):
    if isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value) or not low <= value <= high:
        raise ValueError(f"{name} must be a finite number from {low} to {high}")
    return float(value)


def _keys(row, allowed, name):
    if not isinstance(row, dict) or set(row) - set(allowed):
        raise ValueError(f"Invalid {name} fields")


def resolve_performance(lines, script_sha256, plan=None, *, speed=1.06, gap=.18):
    """Return source-bound line/phrase controls, or preserve the legacy defaults.

    Pauses are inserted PCM silence, in addition to any silence the TTS model
    generates. Caption consumers must use the resulting measured phrase bounds.
    """
    speed = _number(speed, "speed", .8, 1.3)
    gap = _number(gap, "gap", 0, .6)
    if plan is None:
        plan = {"schema": "famtastic.narration-performance.v1", "source_sha256": script_sha256,
                "lines": [{"index": i} for i in range(len(lines))]}
    _keys(plan, ("schema", "source_sha256", "lines"), "performance")
    if plan.get("schema") != "famtastic.narration-performance.v1" or plan.get("source_sha256") != script_sha256:
        raise ValueError("Performance must bind the exact current script SHA-256")
    if not isinstance(plan.get("lines"), list) or len(plan["lines"]) != len(lines):
        raise ValueError("Performance requires one ordered entry per source line")
    resolved = []
    for index, (text, entry) in enumerate(zip(lines, plan["lines"])):
        _keys(entry, ("index", "speed", "pause_after_seconds", "phrases"), "line")
        if type(entry.get("index")) is not int or entry["index"] != index:
            raise ValueError("Performance line indices must match source order")
        rate = _number(entry.get("speed", speed), "line speed", .8, 1.3)
        pause = _number(entry.get("pause_after_seconds", gap if index < len(lines)-1 else 0), "line pause", 0, 1.2)
        if index == len(lines)-1 and pause:
            raise ValueError("The final line pause must be zero; add any video tail separately")
        phrases = entry.get("phrases", [{"text": text}])
        if not isinstance(phrases, list) or not 1 <= len(phrases) <= 32:
            raise ValueError("A line needs 1-32 complete thought phrases")
        parts = []
        for pindex, phrase in enumerate(phrases):
            _keys(phrase, ("text", "speed", "pause_after_seconds"), "phrase")
            if not isinstance(phrase.get("text"), str) or not phrase["text"].strip():
                raise ValueError("Phrase text must be nonempty")
            p_speed = _number(phrase.get("speed", rate), "phrase speed", .8, 1.3)
            p_pause = _number(phrase.get("pause_after_seconds", 0), "phrase pause", 0, 1.2)
            if pindex == len(phrases)-1 and p_pause:
                raise ValueError("Put the last phrase pause on its line to avoid double pauses")
            parts.append({"text": phrase["text"].strip(), "speed": p_speed, "pause_after_seconds": p_pause})
        if " ".join(" ".join(p["text"] for p in parts).split()) != " ".join(text.split()):
            raise ValueError(f"Performance phrases changed source line {index}")
        resolved.append({"index": index, "source_text": text, "speed": rate,
                         "pause_after_seconds": pause, "phrases": parts})
    return resolved


def join_pcm16(chunks, sample_rate=24000):
    """Join mono PCM16 phrases with exact gaps; return actual sample boundaries."""
    if type(sample_rate) is not int or sample_rate <= 0 or not chunks:
        raise ValueError("Positive sample rate and nonempty chunks required")
    output = bytearray(); cues = []; frames = 0
    for index, (pcm, pause) in enumerate(chunks):
        if not isinstance(pcm, bytes) or not pcm or len(pcm) % 2:
            raise ValueError("Each phrase needs nonempty, whole PCM16 samples")
        pause = _number(pause, "PCM pause", 0, 1.2)
        if index == len(chunks)-1 and pause:
            raise ValueError("No trailing pause inside a source line")
        start = frames
        output.extend(pcm); frames += len(pcm)//2
        silence = round(pause * sample_rate)
        cues.append({"start_frame": start, "end_frame": frames, "pause_frames": silence,
                     "start": start/sample_rate, "end": frames/sample_rate})
        output.extend(b"\0\0" * silence); frames += silence
    return bytes(output), cues
