"""Speech-only brand pronunciation. Captions always retain the authored copy."""
from __future__ import annotations
import re

BRAND_IPA = "fæmtˈæstɪk"
DICTIONARY_IPA = "fæntˈæstɪk"


def speech_text(source: str) -> tuple[str, int]:
    text = source.replace("FAMtasticDesigns.com", "FAMtastic Designs dot com")
    text = text.replace("$199", "one hundred ninety-nine dollar")
    text = text.translate(str.maketrans({"’": "'", "“": "", "”": "", "…": "..."}))
    # Use a known dictionary word only to obtain the surrounding sentence's
    # phonemes. Replace its /n/ with /m/ before any audio is synthesized.
    if re.search(r"\bfantastic\b", text, re.I):
        raise ValueError("Literal 'fantastic' is ambiguous with the brand placeholder")
    return re.subn(r"\bFAMtastic\b", "fantastic", text)


def phonemes_for_line(tokenizer, source: str) -> dict:
    text, count = speech_text(source)
    phonemes = tokenizer.phonemize(text, lang="en-us")
    if phonemes.count(DICTIONARY_IPA) != count:
        raise ValueError("Phonemizer output changed; refuse an unverified brand pronunciation")
    explicit = phonemes.replace(DICTIONARY_IPA, BRAND_IPA)
    return {"source_text": source, "phonemizer_input": text,
            "phonemes": explicit, "brand_occurrences": count,
            "brand_pronunciation": "fam-TAS-tik", "brand_ipa": BRAND_IPA}
