/**
 * FAMtastic Designs — Adobe Photoshop Batch Multi-Format Social Exporter
 * Campaign: "Cost Is Not The Reason" ($199 Web Basics Launch)
 * 
 * Usage:
 * In Adobe Photoshop, go to File > Scripts > Browse... and select this .jsx file.
 * 
 * Features:
 * 1. Automatically prompts for input image / folder.
 * 2. Processes into 3 standard delivery formats:
 *    - 1:1 Square Feed (1080x1080px) for Instagram & Facebook
 *    - 9:16 Vertical Story / Reel (1080x1920px) with centered safe-area framing
 *    - 16:9 Landscape (1920x1080px) for Web, X, and YouTube
 * 3. Applies high-quality JPEG compression and saves to /exports/ subfolder.
 */

#target photoshop

function runPhotoshopExporter() {
    if (app.documents.length === 0) {
        var inputFile = File.openDialog("Select Campaign Image to Export Multi-Format", "*.jpg;*.png;*.psd;*.tif");
        if (!inputFile) return;
        app.open(inputFile);
    }

    var doc = app.activeDocument;
    var origName = doc.name.replace(/\.[^\.]+$/, "");
    var docPath = doc.path;
    var exportFolder = new Folder(docPath + "/exports");
    if (!exportFolder.exists) {
        exportFolder.create();
    }

    var formats = [
        { name: "1x1_Square_1080x1080", width: 1080, height: 1080 },
        { name: "9x16_Vertical_1080x1920", width: 1080, height: 1920 },
        { name: "16x9_Landscape_1920x1080", width: 1920, height: 1080 }
    ];

    // Save history state
    var initialHistoryState = doc.activeHistoryState;

    for (var i = 0; i < formats.length; i++) {
        var fmt = formats[i];
        doc.activeHistoryState = initialHistoryState;

        // Duplicate for non-destructive processing
        var tempDoc = doc.duplicate("temp_" + fmt.name, true);
        
        // Resize & Canvas Crop
        fitAndCropCanvas(tempDoc, fmt.width, fmt.height);

        // Export JPEG
        var saveFile = new File(exportFolder + "/" + origName + "_" + fmt.name + ".jpg");
        var exportOptions = new ExportOptionsSaveForWeb();
        exportOptions.format = SaveDocumentType.JPEG;
        exportOptions.includeProfile = true;
        exportOptions.interlaced = false;
        exportOptions.optimized = true;
        exportOptions.quality = 85; // 85% is ideal for web performance

        tempDoc.exportDocument(saveFile, ExportType.SAVEFORWEB, exportOptions);
        tempDoc.close(SaveOptions.DONOTSAVECHANGES);
    }

    alert("FAMtastic Adobe Exporter Complete!\nAll 3 social formats saved to:\n" + exportFolder.fsName);
}

function fitAndCropCanvas(doc, targetWidth, targetHeight) {
    var targetRatio = targetWidth / targetHeight;
    var currentRatio = doc.width.value / doc.height.value;

    if (currentRatio > targetRatio) {
        // Document is wider than target: fit to height
        doc.resizeImage(null, UnitValue(targetHeight, "px"), null, ResampleMethod.BICUBICAUTOMATIC);
    } else {
        // Document is taller than target: fit to width
        doc.resizeImage(UnitValue(targetWidth, "px"), null, null, ResampleMethod.BICUBICAUTOMATIC);
    }

    // Resize Canvas to target bounds (centers the image)
    doc.resizeCanvas(UnitValue(targetWidth, "px"), UnitValue(targetHeight, "px"), AnchorPosition.MIDDLECENTER);
}

runPhotoshopExporter();
