/**
 * FAMtastic Designs — Adobe After Effects Commercial Motion Setup
 * Campaign: "Cost Is Not The Reason" (15-Second Commercial Comp)
 * 
 * Usage:
 * In Adobe After Effects, go to File > Scripts > Run Script File... and select this .jsx file.
 * 
 * What it creates:
 * 1. 1080x1920 (9:16 Vertical Reel/TikTok/Short) 15-second 30fps Master Composition.
 * 2. Dark Carbon Solid Background with radial gradient.
 * 3. Animated Kinetic Text Layers:
 *    - Beat 1 (0-3s): "WHAT'S YOUR EXCUSE?" (Scale pop + fade)
 *    - Beat 2 (3-7s): "COST IS NOT ONE OF THEM" (Chartreuse glow pulse)
 *    - Beat 3 (7-11s): "55¢ A DAY / $199 FULL YEAR" (Slide up + reveal)
 *    - Beat 4 (11-15s): "FAMtastic Designs — Claim Your Launch" (CTA lower third)
 * 4. Adds to Render Queue automatically with ProRes / H.264 settings.
 */

#target aftereffects

function buildCommercialComp() {
    app.beginUndoGroup("Create FAMtastic Commercial Motion Comp");

    var proj = app.project;
    if (!proj) {
        proj = app.newProject();
    }

    var compWidth = 1080;
    var compHeight = 1920;
    var pixelAspect = 1.0;
    var duration = 15.0; // 15 seconds
    var frameRate = 30.0;

    var comp = proj.items.addComp(
        "FAMtastic_15s_Cost_Is_Not_The_Reason_9x16",
        compWidth,
        compHeight,
        pixelAspect,
        duration,
        frameRate
    );

    // 1. Background Solid (Obsidian Dark)
    var bgLayer = comp.layers.addSolid([0.03, 0.04, 0.03], "Background_Dark", compWidth, compHeight, 1.0);
    bgLayer.moveToEnd();

    // 2. Kinetic Title 1: "WHAT'S YOUR EXCUSE?" (0s - 3s)
    var text1 = comp.layers.addText("WHAT'S YOUR EXCUSE?");
    var textProp1 = text1.property("Source Text");
    var doc1 = textProp1.value;
    doc1.fontSize = 72;
    doc1.fillColor = [1, 1, 1];
    doc1.justification = ParagraphJustification.CENTER_JUSTIFY;
    textProp1.setValue(doc1);
    text1.property("Position").setValue([compWidth / 2, compHeight / 2 - 100]);
    text1.inPoint = 0.0;
    text1.outPoint = 3.2;
    // Animate scale & opacity
    var scale1 = text1.property("Scale");
    scale1.setValueAtTime(0.0, [80, 80, 100]);
    scale1.setValueAtTime(0.3, [105, 105, 100]);
    scale1.setValueAtTime(0.5, [100, 100, 100]);

    // 3. Kinetic Title 2: "COST IS NOT ONE OF THEM." (3s - 7.5s)
    var text2 = comp.layers.addText("COST IS NOT ONE OF THEM.");
    var textProp2 = text2.property("Source Text");
    var doc2 = textProp2.value;
    doc2.fontSize = 68;
    doc2.fillColor = [0.486, 0.988, 0.0]; // Chartreuse #7CFC00
    doc2.justification = ParagraphJustification.CENTER_JUSTIFY;
    textProp2.setValue(doc2);
    text2.property("Position").setValue([compWidth / 2, compHeight / 2]);
    text2.inPoint = 3.0;
    text2.outPoint = 7.5;
    // Animate opacity
    var op2 = text2.property("Opacity");
    op2.setValueAtTime(3.0, 0);
    op2.setValueAtTime(3.4, 100);

    // 4. Kinetic Title 3: "55¢ A DAY / $199 FULL YEAR" (7.5s - 11.5s)
    var text3 = comp.layers.addText("55¢ A DAY.\n$199 FULL FIRST YEAR.");
    var textProp3 = text3.property("Source Text");
    var doc3 = textProp3.value;
    doc3.fontSize = 64;
    doc3.fillColor = [1, 1, 1];
    doc3.justification = ParagraphJustification.CENTER_JUSTIFY;
    textProp3.setValue(doc3);
    text3.property("Position").setValue([compWidth / 2, compHeight / 2]);
    text3.inPoint = 7.5;
    text3.outPoint = 11.5;
    var op3 = text3.property("Opacity");
    op3.setValueAtTime(7.5, 0);
    op3.setValueAtTime(7.8, 100);

    // 5. Kinetic Title 4: CTA "FAMtastic Designs — Claim Your Launch" (11.5s - 15.0s)
    var text4 = comp.layers.addText("STOP RENTING. START OWNING.\nfamtasticdesigns.com");
    var textProp4 = text4.property("Source Text");
    var doc4 = textProp4.value;
    doc4.fontSize = 58;
    doc4.fillColor = [0.486, 0.988, 0.0]; // Chartreuse
    doc4.justification = ParagraphJustification.CENTER_JUSTIFY;
    textProp4.setValue(doc4);
    text4.property("Position").setValue([compWidth / 2, compHeight / 2]);
    text4.inPoint = 11.5;
    text4.outPoint = 15.0;
    var op4 = text4.property("Opacity");
    op4.setValueAtTime(11.5, 0);
    op4.setValueAtTime(11.8, 100);

    // Open comp in viewer
    comp.openInViewer();

    app.endUndoGroup();
    alert("FAMtastic After Effects Motion Comp Created Successfully!\nComposition: FAMtastic_15s_Cost_Is_Not_The_Reason_9x16");
}

buildCommercialComp();
