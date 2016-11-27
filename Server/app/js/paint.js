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
    this.brush = null;

    this.onMouseMove = function (e) {
        if (!isDrawing) {
            return;
        }
        var point = { x: e.pageX - basePoint.x, y: e.pageY - basePoint.y };
        var dist = distanceBetween(lastPoint, point);
        var rad = radianBetween(lastPoint, point);
        for (var i = 0; i < dist; i++) {
            var x = lastPoint.x + (Math.sin(rad) * i) - 16;
            var y = lastPoint.y + (Math.cos(rad) * i) - 16;
            ctx.drawImage(self.brush, x, y);
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
};

PaintingCanvas.prototype.toGrayScale = function (width, height) {
    var grayCanvas = document.createElement("canvas");
    grayCanvas.width = width;
    grayCanvas.height = height;
    var ctx = grayCanvas.getContext("2d");
    ctx.drawImage(this.$canvas[0], 0, 0, grayCanvas.width, grayCanvas.height);
    var image = ctx.getImageData(0, 0, grayCanvas.width, grayCanvas.height);
    var imageData = image.data;
    var imageLength = imageData.length;
    for (var i = 0; i < imageLength; i += 4) {
        var c = imageData[i] - (255 - imageData[i+3]);
        imageData[i] = imageData[i+1] = imageData[i+2] = c;
    }
    image.data = imageData;
    ctx.putImageData(image, 0, 0);
    return grayCanvas;
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
    brushReady.then(function (brushes) {
        paintingCanvas.brush = brushes[0];
        $el.append(paintingCanvas.$canvas);
        $(window).resize(paintingCanvas.onResize);
        paintingCanvas.onResize();

        function toolbox() {
            var html = "";
            html += '<div class="painting-toolbox">';
            html += "<ul>";
            html += '<li><i class="tool-brush button fa fa-paint-brush fa-2x"></i></li>';
            html += '<li><i class="tool-eraser button fa fa-eraser fa-2x"></i></li>';
            html += '<li><i class="tool-clear button fa fa-trash fa-2x"></i></li>';
            html += "</ul>";
            html += "</div>";
            var $toolbox = $($.parseHTML(html));
            var tools = {
                $brush: $toolbox.find(".tool-brush"),
                $eraser: $toolbox.find(".tool-eraser"),
                $clear: $toolbox.find(".tool-clear"),
            };
            tools.$brush.click(function () {
                paintingCanvas.enableEraserMode(false);
                tools.$brush.addClass("button-checked");
                tools.$eraser.removeClass("button-checked");
            });
            tools.$eraser.click(function () {
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
            tools.$brush.click();
            return $toolbox;
        }
        $el.append(toolbox());
    });
    $(".test-item input").attr("disabled", "disabled");
    return paintingCanvas;
};

window.PaintingCanvas = PaintingCanvas;

}(jQuery);
