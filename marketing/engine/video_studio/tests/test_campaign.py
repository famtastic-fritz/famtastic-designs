import copy,json,tempfile,unittest
from pathlib import Path
from fam_video.campaign import load_campaign,CampaignError

class CampaignTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.path=Path(self.tmp.name)/'c.json'
        self.c={'schema':'video-studio.campaign.v1','id':'test','title':'Test','scenes':[{'id':'one','duration':2,'layout':'signal','headline':'Hello'}]}
    def tearDown(self):self.tmp.cleanup()
    def load(self):self.path.write_text(json.dumps(self.c));return load_campaign(self.path)
    def test_duration_and_format(self):
        c=self.load();self.assertEqual((c['width'],c['height'],c['duration']),(1080,1920,2))
    def test_missing_ai_media_fails(self):
        self.c['mode']='ai_footage'
        with self.assertRaises(CampaignError):self.load()
    def test_nan_duration_fails(self):
        self.c['scenes'][0]['duration']=float('nan')
        with self.assertRaises(CampaignError):self.load()
    def test_remote_media_fails(self):
        self.c['scenes'][0]['media']='https://example.com/a.mp4'
        with self.assertRaises(CampaignError):self.load()
    def test_duplicate_ids_fail(self):
        self.c['scenes']*=2
        with self.assertRaises(CampaignError):self.load()
    def test_injected_color_fails(self):
        self.c['scenes'][0]['accent']='red; background:url(https://example.com)'
        with self.assertRaises(CampaignError):self.load()
    def test_external_local_asset_needs_rights(self):
        a=Path(self.tmp.name)/'plate.png';a.write_bytes(b'fixture')
        self.c['scenes'][0].update(media='plate.png',media_kind='image')
        with self.assertRaises(CampaignError):self.load()
        self.c['scenes'][0]['rights']='owned';self.assertEqual(self.load()['scenes'][0]['media'],str(a))
    def test_fractional_frame_rejected(self):
        self.c['scenes'][0]['duration']=1.011
        with self.assertRaises(CampaignError):self.load()
