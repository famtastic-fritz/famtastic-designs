import copy
import hashlib
import json
import tempfile
import unittest
from html.parser import HTMLParser
from pathlib import Path

from fam_video.composition import compile_project


REPO = Path(__file__).resolve().parents[4]


class Tags(HTMLParser):
    def __init__(self, content):
        super().__init__()
        self.tags = []
        self.feed(content)

    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))


class CompositionTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.logo = self.root / 'logo.png'
        self.logo.write_bytes(b'original brand fixture')
        self.brand = {
            'name': 'Example Studio', 'url': 'https://example.test',
            'logo': str(self.logo), 'logo_sha256': hashlib.sha256(self.logo.read_bytes()).hexdigest(),
            'palette': {'background': '#070907', 'text': '#f7f7f4', 'accent': '#7cfc00'},
        }
        self.campaign = {
            'id': 'proof', 'title': 'A useful video', 'fps': 30, 'format': '16:9',
            'width': 1280, 'height': 720,
            'scenes': [
                {'id': name, 'duration': 1.1, 'layout': name, 'headline': 'A real business.', 'body': 'An owned address.'}
                for name in ('signal', 'split', 'monument', 'resolve')
            ],
        }

    def compile(self, campaign=None, brand=None, name='project'):
        return compile_project(campaign or self.campaign, brand or self.brand, self.root / name, REPO)

    def test_repeatable_html_original_asset_and_portable_other_brand(self):
        first = self.compile(name='first')
        second = self.compile(name='second')
        self.assertEqual(first['sha256'], second['sha256'])
        html = Path(first['index_html']).read_text()
        self.assertNotIn('FAMtastic', html)
        self.assertNotIn('famtasticdesigns.com', html)
        self.assertNotIn('https://cdn', html)
        self.assertEqual(self.logo.read_bytes(), (Path(first['project_dir']) / first['assets'][0]['path']).read_bytes())
        self.assertEqual(first['duration'], 4.4)
        self.assertEqual(first['frames'], 132)

    def test_all_formats_have_static_dimensions_timing_and_native_runtime_contract(self):
        for label, width, height in [('9:16', 1080, 1920), ('4:5', 1080, 1350), ('1:1', 1080, 1080), ('16:9', 1920, 1080)]:
            with self.subTest(label=label):
                campaign = dict(self.campaign, format=label, width=width, height=height)
                output = self.compile(campaign, name=label.replace(':', '-'))
                content = Path(output['index_html']).read_text()
                roots = [a for t, a in Tags(content).tags if 'data-composition-id' in a]
                self.assertEqual(len(roots), 1)
                self.assertEqual(roots[0]['data-width'], str(width))
                self.assertEqual(roots[0]['data-duration'], '4.4')
                self.assertIn('data-no-timeline', roots[0])
                self.assertIn('animation.pause()', content)
                self.assertNotIn('requestAnimationFrame', content)
                self.assertEqual([s['start'] for s in output['scenes']], [0, 1.1, 2.2, 3.3])

    def test_text_is_escaped_and_caption_timing_is_exact(self):
        campaign = copy.deepcopy(self.campaign)
        campaign['scenes'][0]['headline'] = '</script><script>alert(1)</script>'
        campaign['captions'] = [{'start': .5, 'end': 2, 'text': 'You & your business <grow>.'}]
        result = self.compile(campaign)
        content = Path(result['index_html']).read_text()
        self.assertNotIn('<script>alert(1)</script>', content)
        self.assertIn('&lt;script&gt;', content)
        self.assertIn('You &amp; your business &lt;grow&gt;.', content)
        caption = next(a for t, a in Tags(content).tags if a.get('id') == 'caption-001')
        self.assertEqual(caption['data-start'], '0.5')
        self.assertEqual(caption['data-duration'], '1.5')
        self.assertEqual(result['caption_count'], 1)

    def test_video_has_separate_timing_and_audio_unique_id(self):
        video, audio = self.root / 'source.mp4', self.root / 'narration.wav'
        video.write_bytes(b'video'); audio.write_bytes(b'audio')
        campaign = copy.deepcopy(self.campaign)
        campaign['scenes'][0].update(media=str(video), media_kind='video', trim_start=1.25)
        campaign['audio'] = str(audio)
        result = self.compile(campaign)
        content = Path(result['index_html']).read_text()
        tags = Tags(content).tags
        media = next(a for t, a in tags if t == 'video')
        self.assertEqual(media['data-media-start'], '1.25')
        self.assertIn('muted', media)
        self.assertIn('playsinline', media)
        self.assertLess(content.index('<video '), content.index('<section '))
        self.assertEqual(next(a['id'] for t, a in tags if t == 'audio'), 'campaign-audio')

    def test_resolve_image_remains_a_visible_background(self):
        campaign = copy.deepcopy(self.campaign)
        campaign['scenes'][-1].update(media=str(self.logo), media_kind='image')
        result = self.compile(campaign)
        content = Path(result['index_html']).read_text()
        self.assertIn('layout-resolve image-scene', content)
        self.assertIn('.layout-resolve.image-scene .concept{display:flex;', content)

    def test_hash_mismatch_and_css_injection_fail(self):
        with self.assertRaisesRegex(ValueError, 'hash'):
            self.compile(brand=dict(self.brand, logo_sha256='0' * 64))
        campaign = copy.deepcopy(self.campaign)
        campaign['scenes'][0]['accent'] = '#fff;display:none'
        with self.assertRaisesRegex(ValueError, 'color'):
            self.compile(campaign)

    def test_canonical_credit_is_injected_inside_video_frame(self):
        brand = dict(self.brand, credit_module=str(REPO / 'scripts/creator-credit.mjs'))
        result = self.compile(brand=brand)
        content = Path(result['index_html']).read_text()
        self.assertIn('data-famtastic-creator-credit="v1"', content)
        self.assertIn('data:image/png;base64,', content)
        self.assertLess(content.index('data-famtastic-creator-credit="v1"'), content.index('</main>'))
        self.assertEqual(content.count('data-famtastic-creator-credit="v1"'), 1)

    def test_outside_caption_and_asset_symlink_are_rejected(self):
        campaign = copy.deepcopy(self.campaign)
        campaign['captions'] = [{'start': 1, 'end': 999, 'text': 'No.'}]
        with self.assertRaisesRegex(ValueError, 'Caption timing'):
            self.compile(campaign)
        path = self.root / 'symlinked'
        path.mkdir(); (path / 'assets').symlink_to(self.root, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, 'symlink'):
            self.compile(name='symlinked')


if __name__ == '__main__':
    unittest.main()
