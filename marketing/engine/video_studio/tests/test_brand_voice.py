import unittest
from fam_video.brand_voice import BRAND_IPA, phonemes_for_line, speech_text


class BrandVoiceTests(unittest.TestCase):
    def test_url_and_price_expand_for_speech_only(self):
        source = "FAMtasticDesigns.com: our $199 Special."
        text, count = speech_text(source)
        self.assertEqual(text, "fantastic Designs dot com: our one hundred ninety-nine dollar Special.")
        self.assertEqual(count, 1)
        self.assertEqual(source, "FAMtasticDesigns.com: our $199 Special.")

    def test_explicit_m_replaces_dictionary_n_and_preserves_source(self):
        class Tokenizer:
            def phonemize(self, text, lang):
                return "ðæts fæntˈæstɪk."
        result = phonemes_for_line(Tokenizer(), "That’s FAMtastic.")
        self.assertEqual(result["phonemes"], "ðæts " + BRAND_IPA + ".")
        self.assertEqual(result["source_text"], "That’s FAMtastic.")
        self.assertNotIn("eɪ", result["phonemes"])

    def test_unexpected_phonemizer_refuses_instead_of_guessing(self):
        class ChangedTokenizer:
            def phonemize(self, text, lang):
                return "fæmtˈeɪstɪk"
        with self.assertRaisesRegex(ValueError, "Phonemizer output changed"):
            phonemes_for_line(ChangedTokenizer(), "FAMtastic")

    def test_ambiguous_unrelated_dictionary_word_is_refused(self):
        with self.assertRaisesRegex(ValueError, "ambiguous"):
            speech_text("FAMtastic is fantastic.")


if __name__ == "__main__": unittest.main()
