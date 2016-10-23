!function ($) {

var dateRegex = /(2\d{3})\/(\d{2})\/(\d{2}) (\d{2}):(\d{2}):(\d{2})/;

function parseDate(str) {
    if (!dateRegex.test(str)) {
        return null;
    }
    return new Date(
        RegExp.$1, parseInt(RegExp.$2, 10) - 1, RegExp.$3,
        RegExp.$4, RegExp.$5, RegExp.$6);
}

$.fn.sortableTable = function () {
    var self = this;
    var activeColumn = {
        index: null,
        order: "",
        $el: $(document.createElement("span")).addClass("table-order"),
    };

    function toggleOrder(order) {
        switch (order) {
        case "asc":
            return "desc";
        case "desc":
            return "asc";
        default:
            return "asc";
        }
    }

    function orderLabel(order) {
        switch (order) {
        case "asc":
            return "▲";
        case "desc":
            return "▼";
        default:
            return "";
        }
    }

    function orderValue(order) {
        switch (order) {
        case "asc":
            return 1;
        case "desc":
            return -1;
        default:
            return null;
        }        
    }

    this.find("thead th").each(function (columnIdx) {
        var $th = $(this);
        var column = {
            $el: $th,
        };

        $th.css({ cursor: "pointer" });
        $th.click(function () {
            if (columnIdx !== activeColumn.index) {
                activeColumn.order = "asc";
            } else {
                activeColumn.order = toggleOrder(activeColumn.order);
            }
            activeColumn.index = columnIdx;
            activeColumn.$el.text(orderLabel(activeColumn.order) + " ");
            if (!$th.is(".table-order")) {
                $th.prepend(activeColumn.$el);
            }

            var tableRowEls = [];
            self.find("tbody tr").each(function () {
                var $tr = $(this);
                var columnValue = $tr.find("td:nth-child(" + (columnIdx + 1) + ")").text();
                var floatValue = parseFloat(columnValue, 10);
                var intValue = parseInt(columnValue, 10);
                var dateValue = parseDate(columnValue);
                var row = {
                    $el: $tr,
                    el: $tr[0],
                    value: null,
                };
                if (dateValue) {
                    row.value = dateValue;
                } else if (!isNaN(floatValue)) {
                    row.value = floatValue;
                } else if (!isNaN(intValue)) {
                    row.value = intValue;
                } else {
                    row.value = columnValue;
                }
                tableRowEls.push(row);
            });
            var sortValue = orderValue(activeColumn.order);
            tableRowEls.sort(function (a, b) {
                if (a.value < b.value) {
                    return -sortValue;
                } else if (a.value > b.value) {
                    return sortValue;
                } else {
                    return 0;
                }
            });
            var rows = document.createDocumentFragment();
            var tbodyEl = self.find("tbody")[0];
            tableRowEls.forEach(function (row) {
                tbodyEl.removeChild(row.el);
                rows.appendChild(row.el);
            });
            tbodyEl.appendChild(rows);
        });
    });

};

}(window.jQuery);