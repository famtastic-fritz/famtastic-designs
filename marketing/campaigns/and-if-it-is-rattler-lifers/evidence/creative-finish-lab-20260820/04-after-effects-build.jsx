/*
  FAMtastic Creative Finish Lab — actual Adobe After Effects project build.
  It creates only the named project in this lab. The source JPEG is never modified.
*/
(function () {
  app.beginUndoGroup("Build Rattler Lifers finish treatment");

  var lab = "/Users/famtastic-fritz/Development/FAMtastic/worktrees/shay-website-delivery-swarm/marketing/campaigns/and-if-it-is-rattler-lifers/evidence/creative-finish-lab-20260820";
  var source = new File(lab + "/02-photoshop-finish.jpg");
  var projectFile = new File(lab + "/04-after-effects-depth-and-light.aep");
  var logFile = new File(lab + "/04-after-effects-script-result.txt");

  if (!source.exists) {
    throw new Error("Finish source not found: " + source.fsName);
  }

  var project = app.project;
  var sourceItem = project.importFile(new ImportOptions(source));
  sourceItem.name = "Photoshop finish source (read-only)";

  var comp = project.items.addComp("Rattler Lifers — AE depth and light", 1920, 1080, 1, 4, 30);
  comp.motionBlur = true;
  comp.frameBlending = true;

  var hero = comp.layers.add(sourceItem);
  hero.name = "Finished still — animated camera drift";
  hero.motionBlur = true;
  var heroTransform = hero.property("ADBE Transform Group");
  var heroScale = heroTransform.property("ADBE Scale");
  var heroPosition = heroTransform.property("ADBE Position");
  heroScale.setValueAtTime(0, [139.6, 139.6]);
  heroScale.setValueAtTime(4, [152.2, 152.2]);
  heroPosition.setValueAtTime(0, [960, 548]);
  heroPosition.setValueAtTime(4, [925, 516]);

  var vignette = comp.layers.addSolid([0.018, 0.035, 0.028], "Vignette depth layer", 1920, 1080, 1, 4);
  vignette.blendingMode = BlendingMode.MULTIPLY;
  vignette.property("ADBE Transform Group").property("ADBE Opacity").setValue(14);

  var sweep = comp.layers.addSolid([1.0, 0.55, 0.12], "Amber light sweep", 520, 1500, 1, 4);
  sweep.blendingMode = BlendingMode.ADD;
  sweep.motionBlur = true;
  var sweepTransform = sweep.property("ADBE Transform Group");
  sweepTransform.property("ADBE Rotate Z").setValue(-17);
  var sweepPosition = sweepTransform.property("ADBE Position");
  var sweepOpacity = sweepTransform.property("ADBE Opacity");
  sweepPosition.setValueAtTime(0, [-340, 540]);
  sweepPosition.setValueAtTime(4, [2250, 540]);
  sweepOpacity.setValueAtTime(0, 0);
  sweepOpacity.setValueAtTime(0.65, 7);
  sweepOpacity.setValueAtTime(3.15, 5);
  sweepOpacity.setValueAtTime(4, 0);

  project.save(projectFile);

  logFile.open("w");
  logFile.writeln("status=created");
  logFile.writeln("project=" + projectFile.fsName);
  logFile.writeln("comp=" + comp.name);
  logFile.writeln("source=" + source.fsName);
  logFile.writeln("duration=4s");
  logFile.writeln("fps=30");
  logFile.close();

  app.endUndoGroup();
}());
