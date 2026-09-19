import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, Path(__file__).with_name(filename))
    module = importlib.util.module_from_spec(spec)
    sys.modules[name] = module
    spec.loader.exec_module(module)
    return module


revisions = load('film_revisions', 'publish-video-revision.py')
fixtures = load('film_publish_fixtures', 'test_publish_no_catch_film.py')


class RevisionPublisherTests(unittest.TestCase):
    def test_arbitrary_name_is_rejected(self):
        for value in ['../index.html', 'no-catch-v2;touch /tmp/x', 'unknown']:
            with self.assertRaises(ValueError): revisions.publisher_for(value)

    def test_both_editions_publish_without_touching_v1_and_refuse_collisions(self):
        for edition, stem in revisions.EDITIONS.items():
            with self.subTest(edition=edition), tempfile.TemporaryDirectory() as temp:
                root = Path(temp)
                public = root / 'public_html/media/films'
                public.mkdir(parents=True)
                old = public / 'whats-the-catch-20260919.mp4'
                old.write_bytes(b'preserve-original-film')
                fixture = fixtures.TestAssets(root)
                for name in fixture.contents:
                    (fixture.directory/name).rename(fixture.directory/name.replace('whats-the-catch-20260919',stem))
                module = revisions.publisher_for(edition)
                assets = module.inspect_assets(fixture.directory,runner=fixtures.ffprobe_runner())
                hashes = [a.sha256 for a in assets]
                pre = fixtures.invoke_remote(module.REMOTE_PREFLIGHT, hashes, root)
                self.assertEqual(fixtures.remote_state(pre.stdout), 'ABSENT')
                stage = '.famtastic-no-catch-' + 'a'*32
                staging = root/stage; staging.mkdir()
                for a in assets: (staging/a.name).write_bytes(a.path.read_bytes())
                result = fixtures.invoke_remote(module.REMOTE_PROMOTE,[stage,*hashes],root)
                self.assertEqual(result.returncode,0,result.stderr)
                self.assertEqual(fixtures.remote_state(result.stdout),'PUBLISHED')
                for a in assets: self.assertEqual((public/a.name).read_bytes(),a.path.read_bytes())
                self.assertEqual(old.read_bytes(),b'preserve-original-film')
                pre = fixtures.invoke_remote(module.REMOTE_PREFLIGHT,hashes,root)
                self.assertEqual(fixtures.remote_state(pre.stdout),'EXACT_MATCH')
                (public/assets[0].name).write_bytes(b'newer-owner-content')
                result = fixtures.invoke_remote(module.REMOTE_PROMOTE,[stage,*hashes],root)
                self.assertNotEqual(result.returncode,0)
                self.assertEqual((public/assets[0].name).read_bytes(),b'newer-owner-content')
                self.assertEqual(old.read_bytes(),b'preserve-original-film')


if __name__ == '__main__': unittest.main()
