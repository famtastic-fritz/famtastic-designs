import importlib.util, pathlib, unittest

MODULE=pathlib.Path(__file__).parents[1]/"human_tester.py"
spec=importlib.util.spec_from_file_location("human_tester",MODULE); human=importlib.util.module_from_spec(spec); spec.loader.exec_module(human)

class HumanTesterTests(unittest.TestCase):
    def test_life_path_three(self): self.assertEqual(human.life_path_from_date("1990-05-06"),3)
    def test_master_33_is_preserved(self): self.assertEqual(human.life_path_from_date("2009-11-11"),33)
    def test_control_mode_collects_no_lens(self): self.assertFalse(human.build_persona(life_path=3,opted_in=False)["numerology"]["enabled"])
    def test_three_promotes_creative_expression(self):
        result=human.evaluate_experience({"id":"x"},life_path=3,opted_in=True)
        self.assertIn("visual directions",[x["idea"] for x in result["creative_prompts"]])
        self.assertTrue(result["commercial_decisions_unchanged"])
    def test_33_adjusts_to_service_without_changing_commerce(self):
        result=human.evaluate_experience({"id":"x"},life_path=33,opted_in=True)
        self.assertIn("service impact",result["lens_tests"])
        self.assertTrue(result["required_control_comparison"])
        self.assertTrue(result["commercial_decisions_unchanged"])
    def test_evidence_context_is_traced_and_compared(self):
        context={"schema":"famtastic.swarm-proof.v1","routine":"website.preview.v2","assertions":{"ok":True},"runs":[{"brief":{"request_id":"fixture:x"},"architecture":{"package":{"sku":"FAM-FOOT-199"}},"addons":[{"sku":"FAM-BRAND","category":"recommended","price_status":"canonical_lookup_required"}]}]}
        result=human.evaluate_experience(context,life_path=3,opted_in=True)
        self.assertTrue(result["context_id"].startswith("evidence:"))
        self.assertEqual(result["control_comparison"]["neutral_commercial_hash"],result["control_comparison"]["lens_commercial_hash"])
        self.assertEqual(result["next_action_confidence"],"needs_evidence")

if __name__=="__main__": unittest.main()
