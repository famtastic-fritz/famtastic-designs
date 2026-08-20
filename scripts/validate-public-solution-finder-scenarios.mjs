import { readFileSync } from 'node:fs';

const finder = readFileSync(new URL('../frontend/src/components/SolutionFinder.jsx', import.meta.url), 'utf8');
const controller = readFileSync(new URL('../backend/web/modules/custom/famtastic_pipeline/src/Controller/PublicRequestController.php', import.meta.url), 'utf8');

const scenarios = {
  'logo-ready': ['brandStatus', "value: 'ready'"],
  'no-logo-declined': ['brandStatus', "value: 'no_logo_no_help'"],
  'logo-help': ['brandStatus', "value: 'help_needed'", 'Logo and Brand Starter add-on'],
  'industry-research': ['industry', 'location'],
  'business-model': ['businessModel'],
  'domain-email': ['domainChoice', 'domainDetails', 'businessEmailNeeds', 'Business Email Setup add-on'],
  'reference-sites': ['referenceSites', 'Sites you like or dislike—and why'],
  'owned-infrastructure': ['domainDetails', 'hosting', 'repository'],
  'unlisted-industry': ["type: 'text'", "name: 'industry'"],
  'unlisted-request': ['customNeeds', 'Anything else you need—even if we do not list it?'],
};

for (const [scenario, markers] of Object.entries(scenarios)) {
  for (const marker of markers) {
    if (!finder.includes(marker)) throw new Error(`${scenario}: Solution Finder is missing ${marker}`);
  }
}

for (const marker of ['answers', 'requestSummary', 'referenceSites', 'domainDetails']) {
  if (!controller.includes(marker)) throw new Error(`Public request persistence is missing ${marker}`);
}

console.log(JSON.stringify({
  status: 'passed',
  classification: 'frontend_contract_validated',
  lane: 'anonymous_solution_finder',
  scenario_count: Object.keys(scenarios).length,
  scenarios: Object.keys(scenarios),
}, null, 2));
