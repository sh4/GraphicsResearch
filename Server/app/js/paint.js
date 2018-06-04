!function () {

function distanceBetween(p1, p2) {
    // 原点からの距離を求める
    return Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
}

function radianBetween(p1, p2) {
    // y / x の逆正接を返す
    return Math.atan2(p2.x - p1.x, p2.y - p1.y);
}

function renderHighlightBrush(texture, img) {
    texture.width = img.width;
    texture.height = img.height;
    var ctx = texture.getContext("2d");
    ctx.clearRect(0, 0, texture.width, texture.height);
    ctx.drawImage(img, 0, 0);
    var image = ctx.getImageData(0, 0, texture.width, texture.height);
    var imageData = image.data; // rgbargba..
    var imageLength = imageData.length;
    for (var i = 0; i < imageLength; i += 4) {
        imageData[i] = 255;
        imageData[i+1] = 255;
    }
    image.data = imageData;
    ctx.putImageData(image, 0, 0);
}

function prepareBrushes() {
    var d = new $.Deferred();
    var brushes = [
        "app/images/brush2.png",
    ];
    var texture = document.createElement("canvas");
    var prepareBrushes = brushes.map(function (brushFile) {
        var d = new $.Deferred();
        var img = new Image();
        img.src = brushFile;
        img.onload = function () {
            renderHighlightBrush(texture, img);
            var brushImg = new Image();
            brushImg.src = texture.toDataURL();
            d.resolve(brushImg);
        };
        img.onerror = function () {
            d.reject();
        };
        return d.promise();
    });
    $.when.apply($, prepareBrushes).then(function () {
        d.resolve(Array.prototype.slice.call(arguments));
    })
    return d.promise();
}

function PaintingCanvas($el) {
    var self = this;
    var isDrawing = false;
    var basePoint;
    var lastPoint;
    var canvas = document.createElement("canvas");
    var resizeCanvas = document.createElement("canvas");
    var ctx = canvas.getContext("2d");
    ctx.lineJoin = "round";
    ctx.lineCap = "round";

    this.$canvas = $(canvas);
    this.isDirty = false;
    this.brush = null;
    this.brushSize = 0;
    this.maskCanvas = null;
    this.checkDirtyTimer = null;

    this.onMouseMove = function (e) {
        if (!isDrawing) {
            return;
        }
        var point = { x: e.pageX - basePoint.x, y: e.pageY - basePoint.y };
        var dist = distanceBetween(lastPoint, point);
        var rad = radianBetween(lastPoint, point);
        for (var i = 0; i < dist; i++) {
            var x = lastPoint.x + (Math.sin(rad) * i) - self.brushSize/2;
            var y = lastPoint.y + (Math.cos(rad) * i) - self.brushSize/2;
            ctx.drawImage(self.brush, x, y, self.brushSize, self.brushSize);
        }
        lastPoint = point;
    };

    this.onMouseUp = function (e) {
        $(document.body)
            .removeClass("invalid-selection");
        $(document)
            .unbind("mouseup", self.onMouseUp)
            .unbind("mousemove", self.onMouseMove);
        isDrawing = false;
        if (ctx.globalCompositeOperation === "source-over") {
            self.isDirty = true;
        }
    };

    this.onMouseDown = function (e) {
        $(document.body)
            .addClass("invalid-selection");
        $(document)
            .bind("mouseup", self.onMouseUp)
            .bind("mousemove", self.onMouseMove);
        isDrawing = true;
        lastPoint = { x: e.pageX - basePoint.x, y: e.pageY - basePoint.y };
    };

    this.onResize = function () {
        resizeCanvas.width = canvas.width;
        resizeCanvas.height = canvas.height;
        var resizeCtx = resizeCanvas.getContext("2d");
        resizeCtx.drawImage(canvas, 0, 0);
        var offset = self.$canvas.offset();
        basePoint = { x: offset.left, y: offset.top };
        canvas.width = $el.width();
        canvas.height = $el.height();
        ctx.drawImage(resizeCanvas, 0, 0, canvas.width, canvas.height);
    };

    this.$canvas.mousedown(this.onMouseDown);
    this.$canvas.addClass("painting-canvas");
}

PaintingCanvas.prototype.enableEraserMode = function (enableEraser) {
    var ctx = this.$canvas[0].getContext("2d");
    ctx.globalCompositeOperation = enableEraser ? "destination-out" : "source-over";
};

PaintingCanvas.prototype.clear = function () {
    var canvas = this.$canvas[0];
    canvas.width = canvas.width;
    canvas.height = canvas.height;
    this.isDirty = false;
};

PaintingCanvas.prototype.isDirtyCanvas = function () {
    return this.isDirty;
};

PaintingCanvas.prototype.setMaskImage = function ($maskImage) {
    if ($maskImage.length === 0) {
        return;
    }
    var maskImage = $maskImage[0];
    var maskCanvas = document.createElement("canvas");
    maskCanvas.width = maskImage.naturalWidth;
    maskCanvas.height = maskImage.naturalHeight;

    var ctx = maskCanvas.getContext("2d");
    ctx.drawImage(maskImage, 0, 0);

    this.maskCanvas = maskCanvas;
};

PaintingCanvas.prototype.toGrayScaleAndMaskTest = function (width, height) {
    var grayCanvas = document.createElement("canvas");
    grayCanvas.width = width;
    grayCanvas.height = height;

    var ctx = grayCanvas.getContext("2d");
    ctx.drawImage(this.$canvas[0], 0, 0, grayCanvas.width, grayCanvas.height);
    var image = ctx.getImageData(0, 0, grayCanvas.width, grayCanvas.height);
    var imageData = image.data;
    var imageLength = imageData.length;
    var ok = false;

    if (this.maskCanvas !== null) {
        var maskCtx = this.maskCanvas.getContext("2d");
        var maskImage = maskCtx.getImageData(0, 0, grayCanvas.width, grayCanvas.height);
        var maskImageData = maskImage.data;
        var filled = 0;
        var white = 0;
        var black = 0;
        var whiteFilled = 0;
        var blackFilled = 0;
        for (var i = 0; i < imageLength; i += 4) {
            var m = maskImageData[i];
            var c = imageData[i] - (255 - imageData[i+3]);
            imageData[i] = imageData[i+1] = imageData[i+2] = c;
            if (c > 0) {
                filled++;
                if (m === 255) whiteFilled++;
                else if (m === 0) blackFilled++;
            }
            if (m === 255) white++;
            else if (m === 0) black++;
        }
        var whiteRatio = whiteFilled / white;
        var blackRatio = blackFilled / black;
        if (whiteRatio >= 0.2 && blackRatio <= 0.5) {
            ok = true;
        }
    } else {
        for (var i = 0; i < imageLength; i += 4) {
            var c = imageData[i] - (255 - imageData[i+3]);
            imageData[i] = imageData[i+1] = imageData[i+2] = c;
        }
        ok = true;
    }

    image.data = imageData;
    ctx.putImageData(image, 0, 0);
    return { 
        canvas: grayCanvas,
        ok: ok,
    };
};

PaintingCanvas.prototype.dispose = function () {
    clearInterval(this.checkDirtyTimer);
    $("#question-submit-button").removeAttr("disabled");
    $(".test-item input").removeAttr("disabled");
    $(".painting-toolbox, .painting-canvas").remove();
};

!function toBlobPolyfill(availableToBlob) {
    if (availableToBlob) {
        PaintingCanvas.toBlob = function (canvasEl, callback, type, quality) {
            return canvasEl.toBlob(callback, type, quality);
        };
    } else {
        PaintingCanvas.toBlob = function (canvasEl, callback, type, quality) {
            var bin = atob(canvasEl.toDataURL(type, quality).split(',')[1]);
            var blob = new Uint8Array(bin.length);
            for (var i = 0, n = bin.length; i < n; i++) {
                blob[i] = bin.charCodeAt(i);
            }
            callback(new Blob([blob], { type: type || "image/png" }));
        };
    }
}(!!HTMLCanvasElement.prototype.toBlob);

var brushReady = prepareBrushes();

window.GS.paint.UI = function ($el) {
    var paintingCanvas = new PaintingCanvas($el);
    paintingCanvas.setMaskImage($el.find(".paint-mask-image"));
    brushReady.then(function (brushes) {
        paintingCanvas.brush = brushes[0];

        var brushSize = paintingCanvas.brush.width;
        var eraserBrushSize = paintingCanvas.brush.width;

        $el.append(paintingCanvas.$canvas);
        $(window).resize(paintingCanvas.onResize);
        paintingCanvas.onResize();

        function selectBrush(size) {
            if (size === undefined) {
                paintingCanvas.brushSize = brushSize;
            } else {
                paintingCanvas.brushSize = brushSize = size;
            }
        }

        function selectEraser(size) {
            if (size === undefined) {
                paintingCanvas.brushSize = eraserBrushSize;
            } else {
                paintingCanvas.brushSize = eraserBrushSize = size;
            }
        }

        function expandableToolHtml(toolHtml, expandToolClassName) {
            var html = "";
            html += '<table class="painting-expandable-tool">';
            html += '<tr>'
            html += '<td>';
            html += toolHtml;
            html += '</td>';
            html += '<td class="button"><i class="';
            html += expandToolClassName;
            html += ' fa fa-caret-right"></i></td>';
            html += '<td class="painting-brush-selection">';
            html += '<a class="paint-brush-item" data-brush-size="32" href="javascript:void(0)"><img src="app/images/brush2.png" width="16" height="16" style="top:16px"></a>';
            html += '<a class="paint-brush-item" data-brush-size="48" href="javascript:void(0)"><img src="app/images/brush2.png" width="32" height="32" style="top:6px"></a>';
            html += '<a class="paint-brush-item" data-brush-size="64" href="javascript:void(0)"><img src="app/images/brush2.png" width="42" height="42"></a>';
            html += '</td>';
            html += '</tr>';
            html += '</table>';
            return html;
        }

        function clickExpandTool(callback) {
            return function (e) {
                function hideMenu() {
                    $(document).unbind("click", hideMenu);
                    $button.removeClass("button-checked");
                    $expand.hide();
                }
                var $button = $(this).parents("td.button");
                var $expand = $(this).parents(".painting-expandable-tool:first").find(".painting-brush-selection");
                if ($expand.is(":visible")) {
                    hideMenu();
                    return;
                }
                $button.addClass("button-checked");
                callback($expand);
                $expand.show();
                setTimeout(function () {
                    $(document).bind("click", hideMenu);
                }, 0);
            };
        }

        function toolbox() {
            var html = "";
            html += '<div class="painting-toolbox">';
            html += "<ul>";
            html += '<li>';
            html += expandableToolHtml('<i title="Painting Brush" class="tool-brush button fa fa-paint-brush fa-2x"></i>', 'tool-brush-expand');
            html += '</li>';
            html += '<li>';
            html += expandableToolHtml('<i title="Eraser" class="tool-eraser button fa fa-eraser fa-2x"></i>', 'tool-eraser-expand');
            html += '</li>';
            html += '<li><i title="Clear Painting" class="tool-clear button fa fa-trash fa-2x"></i></li>';
            html += "</ul>";
            html += "</div>";
            var $toolbox = $($.parseHTML(html));
            var tools = {
                $brush: $toolbox.find(".tool-brush"),
                $eraser: $toolbox.find(".tool-eraser"),
                $clear: $toolbox.find(".tool-clear"),
                $brushExpand: $toolbox.find(".tool-brush-expand"),
                $eraserExpand: $toolbox.find(".tool-eraser-expand"),
            };
            tools.$brush.click(function () {
                selectBrush();
                paintingCanvas.enableEraserMode(false);
                tools.$brush.addClass("button-checked");
                tools.$eraser.removeClass("button-checked");
            });
            tools.$eraser.click(function () {
                selectEraser();
                paintingCanvas.enableEraserMode(true);
                tools.$eraser.addClass("button-checked");
                tools.$brush.removeClass("button-checked");
            });
            tools.$clear.click(function () {
                if (!confirm("Do you really want to clear painting?")) {
                    return;
                }
                paintingCanvas.clear();
            });
            tools.$brushExpand.parents(".painting-expandable-tool").on("click", ".paint-brush-item", function () {
                selectBrush($(this).data("brush-size"));
            });
            tools.$brushExpand.click(clickExpandTool(function ($expand) {
                $expand.find("a").removeClass("active");
                $expand.find('a[data-brush-size="'+brushSize+'"]').addClass("active");
            }));
            tools.$eraserExpand.click(clickExpandTool(function ($expand) {
                $expand.find("a").removeClass("active");
                $expand.find('a[data-brush-size="'+eraserBrushSize+'"]').addClass("active");
            }));
            tools.$eraserExpand.parents(".painting-expandable-tool").on("click", ".paint-brush-item", function () {
                selectEraser($(this).data("brush-size"));
            });
            tools.$brush.click();
            return $toolbox;
        }
        $el.append(toolbox());
    });
    $(".test-item input").attr("disabled", "disabled");
    $("#question-submit-button").attr("disabled", "disabled");
    paintingCanvas.checkDirtyTimer = setInterval(function () {
        if (paintingCanvas.isDirtyCanvas()) {
            $("#question-submit-button").removeAttr("disabled");
        } else {
            $("#question-submit-button").attr("disabled", "disabled");
        }
    }, 500);
    return paintingCanvas;
};

window.PaintingCanvas = PaintingCanvas;

}(jQuery);
