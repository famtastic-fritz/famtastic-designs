import json,tempfile,unittest
from pathlib import Path
from fam_video.cli import valid_cached_evidence,make_audio
from fam_video.campaign import sha256
class CacheTests(unittest.TestCase):
    def test_cache_requires_unchanged_ledger_and_all_artifacts(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);asset=root/'source.html';asset.write_text('original')
            dna=root/'build-dna.json';dna.write_text(json.dumps({'artifacts':[{'path':'source.html','sha256':sha256(asset)}],'completion':{'status':'gated'}}))
            hit={'build_dna':str(dna),'build_dna_sha256':sha256(dna)}
            self.assertTrue(valid_cached_evidence(hit,root))
            asset.write_text('changed');self.assertFalse(valid_cached_evidence(hit,root))
            asset.write_text('original');dna.write_text('{}');self.assertFalse(valid_cached_evidence(hit,root))
    def test_cache_rejects_failed_completion(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);asset=root/'source';asset.write_text('x');dna=root/'build-dna.json'
            dna.write_text(json.dumps({'artifacts':[{'path':'source','sha256':sha256(asset)}],'completion':{'status':'failed'}}))
            self.assertFalse(valid_cached_evidence({'build_dna':str(dna),'build_dna_sha256':sha256(dna)},root))
    def test_demo_audio_does_not_overwrite(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'pulse.wav';make_audio(path,.5)
            with self.assertRaises(ValueError):make_audio(path,.5)
