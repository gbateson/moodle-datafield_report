(function() {
    var RPT = {};

    RPT.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent("on" + evt, fn);
        }
    };

    RPT.wwwroot = location.href.replace(new RegExp("/mod/data/.*$"), "")

    RPT.fix_page_id = function() {
        var body = document.getElementById("path-mod-data-field-");
        var type = document.querySelector("form#editfield input[type=hidden][name=type]");
        if (body && type) {
            body.id += type.value;
        }
    };

    RPT.setup_section_toggle = function(){
        document.querySelectorAll("h4").forEach(function(h4){

            var ul = h4.nextElementSibling;
            if (ul && ul.matches("ul")) {

                // offsetHeight doesn't include margins, so we add an extra 16px
                ul.dataset.originalheight = (ul.offsetHeight + 16);

                var img = document.createElement("IMG");
                img.src = RPT.wwwroot + "/pix/t/less.svg";
                img.className = "bg-light border rounded p-2 ml-3";

                h4.appendChild(img);

                RPT.add_event_listener(img, "click", function(){
                    var ul = this.parentNode.nextElementSibling;
                    if (this.src.indexOf("/less.svg") >= 0) {
                        this.src = this.src.replace("/less.svg", "/more.svg");
                        // https://stackoverflow.com/questions/3331353/transitions-on-the-css-display-property
                        // try transition on "max-height" instead of "height"
                        ul.style.overflow = "hidden";
                        ul.style.opacity = 0;
                        ul.style.maxHeight = 0;
                        ul.style.transition = "max-height 1s ease 0s, opacity 1s ease 0s";
                    } else {
                        this.src = this.src.replace("/more.svg", "/less.svg");
                        ul.style.overflow = "auto";
                        ul.style.opacity = 1;
                        ul.style.maxHeight = ul.dataset.originalheight + "px";
                        ul.style.transition = "max-height 1s ease 0s, opacity 1s ease 0s";
                    }
                });

                img.dispatchEvent(new Event("click"));
            }
        });
    };

    RPT.setup = function() {
        RPT.fix_page_id();
        RPT.setup_section_toggle();
    };

    RPT.add_event_listener(window, "load", RPT.setup);
}());