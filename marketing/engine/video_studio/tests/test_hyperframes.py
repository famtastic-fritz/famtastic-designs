import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from fam_video.adapters import hyperframes


class HyperFramesTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='video adapter ')
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name).resolve()
        (self.path / 'index.html').write_text('<main data-composition-id="sample" data-width="640" data-height="360" data-duration="2"></main>')
        self.calls = []

    def fake_run(self, command, **kwargs):
        self.calls.append(command)
        stdout = ''
        if '--version' in command:
            stdout = '0.8.50'
        elif 'check' in command:
            stdout = '{"ok":true}'
        elif 'doctor' in command:
            stdout = '{"ok":false,"checks":[{"name":"Chrome","ok":false}]}'
        elif 'render' in command:
            Path(command[command.index('--output') + 1]).write_bytes(b'verified-output')
        elif command[0] == '/fake/ffprobe':
            stdout = json.dumps({'streams': [{'codec_type': 'video', 'width': 640, 'height': 360, 'avg_frame_rate': '25/1'}], 'format': {'duration': '2'}})
        return {'stdout': stdout, 'returncode': 0, 'timed_out': False, 'elapsed_seconds': .1}

    def test_missing_binary_does_not_attempt_install(self):
        with patch.object(hyperframes, '_resolve', return_value=None), patch.object(hyperframes, '_run') as run:
            result = hyperframes.inspect('not-a-command')
        self.assertFalse(result['available'])
        run.assert_not_called()

    def test_doctor_payload_matters_even_when_exit_code_is_zero(self):
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), patch.object(hyperframes, '_run', side_effect=self.fake_run):
            result = hyperframes.inspect()
        self.assertTrue(result['available'])
        self.assertFalse(result['ok'])

    def test_advisory_update_and_optional_capability_failures_do_not_block_rendering(self):
        names = ['Node.js', 'CPU', 'Memory', 'Disk', 'Frames cache', 'Archive extractor', 'Environment',
                 'FFmpeg', 'FFprobe', 'Chrome']
        diagnosis = {'ok': False, 'checks': [{'name': name, 'ok': True, 'detail': 'ready'} for name in names] + [
            {'name': 'Version', 'ok': False, 'detail': '0.8.29 -> 0.8.50 available'},
            {'name': 'TTS (Kokoro)', 'ok': False, 'detail': 'Optional'},
            {'name': 'BGM (MusicGen)', 'ok': False, 'detail': 'Optional'},
            {'name': 'whisper-cpp', 'ok': False, 'detail': 'Optional transcription integration'},
            {'name': 'Docker', 'ok': False, 'detail': 'Optional container integration'},
            {'name': 'Docker running', 'ok': False, 'detail': 'Optional container integration'},
        ]}
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), \
             patch.object(hyperframes, '_run', side_effect=lambda command, **kwargs: {
                 'returncode': 0, 'timed_out': False, 'stdout': '0.8.29' if '--version' in command else json.dumps(diagnosis),
             }):
            result = hyperframes.inspect()
        self.assertTrue(result['ok'])
        self.assertEqual(result['doctor'], diagnosis)
        self.assertEqual(result['readiness']['advisory_failures'], [
            'bgm (musicgen)', 'docker', 'docker running', 'tts (kokoro)', 'version', 'whisper-cpp',
        ])

    def test_failed_or_missing_required_doctor_check_blocks_rendering_with_reason(self):
        names = ['Node.js', 'CPU', 'Memory', 'Disk', 'Frames cache', 'Archive extractor', 'Environment',
                 'FFmpeg', 'FFprobe', 'Chrome']
        for checks, expected_reason in (
            ([{'name': name, 'ok': name != 'Chrome'} for name in names], 'chrome'),
            ([{'name': name, 'ok': True} for name in names if name != 'FFprobe'], 'ffprobe'),
        ):
            with self.subTest(expected_reason=expected_reason):
                diagnosis = {'ok': False, 'checks': checks}
                with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), \
                     patch.object(hyperframes, '_run', side_effect=lambda command, **kwargs: {
                         'returncode': 0, 'timed_out': False,
                         'stdout': '0.8.29' if '--version' in command else json.dumps(diagnosis),
                     }):
                    result = hyperframes.inspect()
                self.assertFalse(result['ok'])
                self.assertIn(expected_reason, result['reason'])

    def test_legacy_doctor_aggregate_without_checks_remains_supported(self):
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), \
             patch.object(hyperframes, '_run', side_effect=lambda command, **kwargs: {
                 'returncode': 0, 'timed_out': False,
                 'stdout': '0.8.50' if '--version' in command else '{"ok":true}',
             }):
            result = hyperframes.inspect()
        self.assertTrue(result['ok'])
        self.assertEqual(result['doctor'], {'ok': True})

    def test_success_checks_probes_and_atomically_exposes_output_with_25fps(self):
        output = self.path / 'final output.mp4'
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), patch.object(hyperframes.shutil, 'which', return_value='/fake/ffprobe'), patch.object(hyperframes, '_run', side_effect=self.fake_run):
            result = hyperframes.render(self.path, output, fps=25)
        self.assertEqual(output.read_bytes(), b'verified-output')
        self.assertEqual(result['fps'], 25)
        self.assertEqual(result['external_service_cost_usd'], 0)
        self.assertFalse(result['approved_for_publication'])
        render_command = next(c for c in self.calls if 'render' in c)
        self.assertIn('--strict', render_command)
        self.assertEqual(render_command[render_command.index('--workers') + 1], '1')
        self.assertEqual(render_command[2], str(self.path))
        self.assertFalse(any(self.path.glob('.hyperframes-*')))

    def test_failed_check_never_renders_or_exposes_output(self):
        def fail_check(command, **kwargs):
            result = self.fake_run(command, **kwargs)
            if 'check' in command:
                result.update(returncode=1, stdout='missing asset')
            return result
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), patch.object(hyperframes.shutil, 'which', return_value='/fake/ffprobe'), patch.object(hyperframes, '_run', side_effect=fail_check):
            with self.assertRaisesRegex(RuntimeError, 'check exited'):
                hyperframes.render(self.path, self.path / 'out.mp4', fps=25)
        self.assertFalse(any('render' in c for c in self.calls))
        self.assertFalse((self.path / 'out.mp4').exists())

    def test_missing_audio_is_a_failed_movie(self):
        source = self.path / 'index.html'
        source.write_text(source.read_text() + '<audio id="narration" src="local.wav"></audio>')
        with patch.object(hyperframes, '_resolve', return_value='/fake/hyperframes'), patch.object(hyperframes.shutil, 'which', return_value='/fake/ffprobe'), patch.object(hyperframes, '_run', side_effect=self.fake_run):
            with self.assertRaisesRegex(RuntimeError, 'silent'):
                hyperframes.render(self.path, self.path / 'out.mp4', fps=25)
        self.assertFalse((self.path / 'out.mp4').exists())

    def test_existing_movie_is_never_overwritten(self):
        output = self.path / 'out.mp4'
        output.write_bytes(b'precious')
        with self.assertRaises(FileExistsError):
            hyperframes.render(self.path, output)
        self.assertEqual(output.read_bytes(), b'precious')

    def test_env_disables_updates_and_service_telemetry(self):
        env = hyperframes._environment()
        for name in ('HYPERFRAMES_NO_TELEMETRY', 'HYPERFRAMES_NO_UPDATE_CHECK', 'HYPERFRAMES_NO_AUTO_INSTALL'):
            self.assertEqual(env[name], '1')

    def test_quality_aliases_follow_existing_cli_help(self):
        for advertised, requested, expected in [
            ('draft, standard, high', 'delivery', 'high'),
            ('draft, standard, high', 'looks', 'standard'),
            ('draft, looks, delivery', 'delivery', 'delivery'),
            ('draft, looks, delivery', 'high', 'delivery'),
        ]:
            with self.subTest(advertised=advertised, requested=requested):
                with patch.object(hyperframes, '_run', return_value={
                    'returncode': 0, 'timed_out': False,
                    'stdout': '--quality=<quality> Quality: ' + advertised,
                }):
                    self.assertEqual(hyperframes._resolve_quality('/fake/hyperframes', requested), expected)

    def test_unknown_quality_contract_is_not_silently_downgraded(self):
        with patch.object(hyperframes, '_run', return_value={
            'returncode': 0, 'timed_out': False,
            'stdout': '--quality=<quality> Quality: draft, experimental',
        }):
            with self.assertRaisesRegex(RuntimeError, 'does not advertise'):
                hyperframes._resolve_quality('/fake/hyperframes', 'delivery')

    def test_real_child_process_timeout_is_bounded(self):
        result = hyperframes._run([sys.executable, '-c', 'import time; time.sleep(30)'], timeout=.05)
        self.assertTrue(result['timed_out'])
        self.assertNotEqual(result['returncode'], 0)
        self.assertLess(result['elapsed_seconds'], 5)


if __name__ == '__main__':
    unittest.main()
