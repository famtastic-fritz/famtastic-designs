import copy
import unittest

from fam_video.narration_performance import join_pcm16, resolve_performance


class NarrationPerformanceTests(unittest.TestCase):
    def setUp(self):
        self.lines = ['What’s the catch?', 'You grow. We grow.']
        self.plan = {'schema': 'famtastic.narration-performance.v1', 'source_sha256': 'current',
            'lines': [{'index': 0, 'speed': 1.02, 'pause_after_seconds': .42},
                      {'index': 1, 'phrases': [{'text': 'You grow.', 'pause_after_seconds': .3},
                                              {'text': 'We grow.'}]}]}

    def test_legacy_defaults_keep_line_copy_and_no_final_gap(self):
        rows = resolve_performance(self.lines, 'current')
        self.assertEqual([r['pause_after_seconds'] for r in rows], [.18, 0])
        self.assertEqual([r['phrases'][0]['text'] for r in rows], self.lines)
        self.assertEqual(rows[0]['phrases'][0]['speed'], 1.06)

    def test_source_bound_distinct_pause_and_speed_controls(self):
        original = copy.deepcopy(self.plan)
        rows = resolve_performance(self.lines, 'current', self.plan, speed=1.08)
        self.assertEqual(rows[0]['phrases'][0]['speed'], 1.02)
        self.assertEqual(rows[1]['phrases'][1]['speed'], 1.08)
        self.assertEqual(rows[1]['phrases'][0]['pause_after_seconds'], .3)
        self.assertEqual(self.plan, original)

    def test_script_change_and_copy_rewrite_are_rejected(self):
        with self.assertRaisesRegex(ValueError, 'SHA-256'):
            resolve_performance(self.lines, 'changed', self.plan)
        self.plan['lines'][1]['phrases'][0]['text'] = 'We grow.'
        with self.assertRaisesRegex(ValueError, 'changed source'):
            resolve_performance(self.lines, 'current', self.plan)

    def test_bad_order_nan_and_unknown_controls_are_rejected(self):
        for key, value in [('index', 2), ('index', False), ('speed', float('nan')),
                           ('pause_after_seconds', -1), ('emotion', 'happy')]:
            plan = copy.deepcopy(self.plan); plan['lines'][0][key] = value
            with self.subTest(key=key, value=value), self.assertRaises(ValueError):
                resolve_performance(self.lines, 'current', plan)

    def test_double_pause_and_final_pause_are_rejected(self):
        self.plan['lines'][1]['phrases'][-1]['pause_after_seconds'] = .2
        with self.assertRaisesRegex(ValueError, 'double pauses'):
            resolve_performance(self.lines, 'current', self.plan)
        self.plan['lines'][1].pop('phrases')
        self.plan['lines'][1]['pause_after_seconds'] = .2
        with self.assertRaisesRegex(ValueError, 'final line pause'):
            resolve_performance(self.lines, 'current', self.plan)

    def test_sample_exact_gap_and_cue_alignment(self):
        first = b'\x01\x00' * 240; second = b'\x02\x00' * 480
        pcm, cues = join_pcm16([(first, .25), (second, 0)])
        self.assertEqual(pcm, first + b'\0\0' * 6000 + second)
        self.assertEqual(cues[0]['end'], .01)
        self.assertEqual(cues[1]['start'], .26)
        self.assertEqual(cues[1]['end'], len(pcm)/2/24000)
        self.assertEqual(cues[0]['pause_frames'], 6000)

    def test_malformed_pcm_is_not_silently_shifted(self):
        with self.assertRaises(ValueError): join_pcm16([(b'\x00', 0)])
        with self.assertRaises(ValueError): join_pcm16([(b'\0\0', .3)])


if __name__ == '__main__': unittest.main()
