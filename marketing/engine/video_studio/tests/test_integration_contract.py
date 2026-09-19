import json,subprocess,tempfile,unittest
from pathlib import Path
from fam_video.campaign import CampaignError,load_campaign
from fam_video.cli import parser
class ImportedMediaContract(unittest.TestCase):
    def test_video_duration_and_sound_policy(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);clip=root/'clip.mp4'
            subprocess.run(['ffmpeg','-v','error','-f','lavfi','-i','color=c=blue:s=320x320:r=30:d=1','-f','lavfi','-i','sine=frequency=200:duration=1','-c:v','libx264','-threads','1','-c:a','aac','-shortest',str(clip)],check=True)
            c={'schema':'video-studio.campaign.v1','id':'media','title':'Media','mode':'editorial','scenes':[{'id':'one','duration':1,'layout':'split','headline':'Test','media':'clip.mp4','media_kind':'video','rights':'owned'}]};path=root/'c.json'
            path.write_text(json.dumps(c))
            with self.assertRaisesRegex(CampaignError,'Source contains audio'):load_campaign(path)
            c['scenes'][0]['source_audio']='discard';path.write_text(json.dumps(c));load_campaign(path)
            c['scenes'][0]['trim_start']=.5;path.write_text(json.dumps(c))
            with self.assertRaisesRegex(CampaignError,'too short'):load_campaign(path)
    def test_generation_cli_requires_real_workflow(self):
        with self.assertRaises(SystemExit):parser().parse_args(['generate'])
