import json,tempfile,unittest
from pathlib import Path
from fam_video.cli import valid_cached_evidence,make_audio
from fam_video.campaign import sha256
class CacheTests(unittest.TestCase):
    def make_hit(self,root,completion_status='gated',integrity='passed',stage_status='passed'):
        root=Path(root).resolve();asset=root/'source.html';asset.write_text('original')
        dna=root/'build-dna.json'
        dna.write_text(json.dumps({
            'schema':'famtastic.build-dna.v1',
            'stages':[{'stage_id':'render','result':{'status':stage_status}}],
            'artifacts':[{'path':'source.html','sha256':sha256(asset)}],
            'completion':{'status':completion_status,'integrity':integrity},
        }))
        return {'build_dna':str(dna),'build_dna_sha256':sha256(dna)},asset,dna

    def test_cache_requires_unchanged_ledger_and_all_artifacts(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);hit,asset,dna=self.make_hit(root)
            self.assertTrue(valid_cached_evidence(hit,root))
            asset.write_text('changed');self.assertFalse(valid_cached_evidence(hit,root))
            asset.write_text('original');dna.write_text('{}');hit['build_dna_sha256']=sha256(dna)
            self.assertFalse(valid_cached_evidence(hit,root))

    def test_cache_rejects_failed_completion(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);hit,_,_=self.make_hit(root,completion_status='failed')
            self.assertFalse(valid_cached_evidence(hit,root))

    def test_cache_rejects_incomplete_or_unverified_completion(self):
        for status,integrity in [('in_progress','passed'),('gated','failed'),('gated',None)]:
            with self.subTest(status=status,integrity=integrity),tempfile.TemporaryDirectory() as temp:
                root=Path(temp);hit,_,_=self.make_hit(root,completion_status=status,integrity=integrity)
                self.assertFalse(valid_cached_evidence(hit,root))

    def test_cache_rejects_failed_or_running_stage(self):
        for status in ('failed','running'):
            with self.subTest(status=status),tempfile.TemporaryDirectory() as temp:
                root=Path(temp);hit,_,_=self.make_hit(root,stage_status=status)
                self.assertFalse(valid_cached_evidence(hit,root))

    def test_demo_audio_does_not_overwrite(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'pulse.wav';make_audio(path,.5)
            with self.assertRaises(ValueError):make_audio(path,.5)
