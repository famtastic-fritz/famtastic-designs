/**
 * FAMtastic Designs — Automated Photoshop Batch Multi-Format Exporter
 * Runs unattended: processes all campaign images in marketing/campaigns/cost-is-not-the-reason/images/
 */

#target photoshop

function autoRunPhotoshopBatch() {
    app.displayDialogs = DialogModes.NO;

    var inputFolderPath = "/Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/marketing/campaigns/cost-is-not-the-reason/images";
    var inputFolder = new Folder(inputFolderPath);
    if (!inputFolder.exists) {
        return "Input folder does not exist: " + inputFolderPath;
    }

    var exportFolder = new Folder(inputFolderPath + "/exports");
    if (!exportFolder.exists) {
        exportFolder.create();
    }

    var files = inputFolder.getFiles(/\.(jpg|jpeg|png)$/i);
    var processedCount = 0;

    var formats = [
        { name: "1x1_Square_1080x1080", width: 1080, height: 1080 },
        { name: "9x16_Vertical_1080x1920", width: 1080, height: 1920 },
        { name: "16x9_Landscape_1920x1080", width: 1920, height: 1080 }
    ];

    for (var f = 0; f < files.length; f++) {
        var file = files[f];
        if (file instanceof File) {
            var doc = app.open(file);
            var origName = doc.name.replace(/\.[^\.]+$/, "");

            for (var i = 0; i < formats.length; i++) {
                var fmt = formats[i];
                var tempDoc = doc.duplicate("temp_" + fmt.name, true);

                fitAndCrop(tempDoc, fmt.width, fmt.height);

                var saveFile = new File(exportFolder + "/" + origName + "_" + fmt.name + ".jpg");
                var exportOptions = new ExportOptionsSaveForWeb();
                exportOptions.format = SaveDocumentType.JPEG;
                exportOptions.includeProfile = true;
                exportOptions.interlaced = false;
                exportOptions.optimized = true;
                exportOptions.quality = 85;

                tempDoc.exportDocument(saveFile, ExportType.SAVEFORWEB, exportOptions);
                tempDoc.close(SaveOptions.DONOTSAVECHANGES);
            }

            doc.close(SaveOptions.DONOTSAVECHANGES);
            processedCount++;
        }
    }

    return "Successfully processed " + processedCount + " campaign images into 1:1, 9:16, and 16:9 formats in " + exportFolder.fsName;
}

function fitAndCrop(doc, targetWidth, targetHeight) {
    var targetRatio = targetWidth / targetHeight;
    var currentRatio = doc.width.value / doc.height.value;

    if (currentRatio > targetRatio) {
        doc.resizeImage(null, UnitValue(targetHeight, "px"), null, ResampleMethod.BICUBICAUTOMATIC);
    } else {
        doc.resizeImage(UnitValue(targetWidth, "px"), null, null, ResampleMethod.BICUBICAUTOMATIC);
    }

    doc.resizeCanvas(UnitValue(targetWidth, "px"), UnitValue(targetHeight, "px"), AnchorPosition.MIDDLECENTER);
}

autoRunPhotoshopBatch();
