/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "../demo8/src/js/custom/documentation/general/datatables/subtable.js":
/*!***************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/datatables/subtable.js ***!
  \***************************************************************************/
/***/ ((module) => {



// Class definition
var KTDocsDatatableSubtable = function () {
    var table;
    var datatable;
    var template;

    // Private methods
    const initDatatable = () => {
        // Set date data order
        const tableRows = table.querySelectorAll('tbody tr');

        tableRows.forEach(row => {
            const dateRow = row.querySelectorAll('td');
            const realDate = moment(dateRow[1].innerHTML, "DD MMM YYYY, LT").format(); // select date from 2nd column in table

            // Skip template
            if (!row.closest('[data-kt-docs-datatable-subtable="subtable_template"]')) {
                dateRow[1].setAttribute('data-order', realDate);
                dateRow[1].innerText = moment(realDate).fromNow();
            }
        });

        // Get subtable template
        const subtable = document.querySelector('[data-kt-docs-datatable-subtable="subtable_template"]');
        template = subtable.cloneNode(true);
        template.classList.remove('d-none');

        // Remove subtable template
        subtable.parentNode.removeChild(subtable);

        // Init datatable --- more info on datatables: https://datatables.net/manual/
        datatable = $(table).DataTable({
            "info": false,
            'order': [],
            "lengthChange": false,
            'pageLength': 6,
            'ordering': false,
            'paging': false,
            'columnDefs': [
                { orderable: false, targets: 0 }, // Disable ordering on column 0 (checkbox)
                { orderable: false, targets: 6 }, // Disable ordering on column 6 (actions)
            ]
        });

        // Re-init functions on every table re-draw -- more info: https://datatables.net/reference/event/draw
        datatable.on('draw', function () {
            resetSubtable();
            handleActionButton();
        });
    }

    // Subtable data sample
    const data = [
        {
            image: '76',
            name: 'Go Pro 8',
            description: 'Latest  version of Go Pro.',
            cost: '500.00',
            qty: '1',
            total: '500.00',
            stock: '12'
        },
        {
            image: '60',
            name: 'Bose Earbuds',
            description: 'Top quality earbuds from Bose.',
            cost: '300.00',
            qty: '1',
            total: '300.00',
            stock: '8'
        },
        {
            image: '211',
            name: 'Dry-fit Sports T-shirt',
            description: 'Comfortable sportswear for everyday use.',
            cost: '89.00',
            qty: '1',
            total: '89.00',
            stock: '18'
        },
        {
            image: '21',
            name: 'Apple Airpod 3',
            description: 'Apple\'s latest and most advanced earbuds.',
            cost: '200.00',
            qty: '2',
            total: '400.00',
            stock: '32'
        },
        {
            image: '83',
            name: 'Nike Pumps',
            description: 'Apple\'s latest and most advanced headphones.',
            cost: '200.00',
            qty: '1',
            total: '200.00',
            stock: '8'
        }
    ];

    // Handle action button
    const handleActionButton = () => {
        const buttons = document.querySelectorAll('[data-kt-docs-datatable-subtable="expand_row"]');

        // Sample row items counter --- for demo purpose only, remove this variable in your project
        const rowItems = [4, 1, 5, 1, 4, 2];

        buttons.forEach((button, index) => {
            button.addEventListener('click', e => {
                e.stopImmediatePropagation();
                e.preventDefault();

                const row = button.closest('tr');
                const rowClasses = ['isOpen', 'border-bottom-0'];

                // Get total number of items to generate --- for demo purpose only, remove this code snippet in your project
                const demoData = [];
                for (var j = 0; j < rowItems[index]; j++) {
                    demoData.push(data[j]);
                }
                // End of generating demo data

                // Handle subtable expanded state
                if (row.classList.contains('isOpen')) {
                    // Remove all subtables from current order row
                    while (row.nextSibling && row.nextSibling.getAttribute('data-kt-docs-datatable-subtable') === 'subtable_template') {
                        row.nextSibling.parentNode.removeChild(row.nextSibling);
                    }
                    row.classList.remove(...rowClasses);
                    button.classList.remove('active');
                } else {
                    populateTemplate(demoData, row);
                    row.classList.add(...rowClasses);
                    button.classList.add('active');
                }
            });
        });
    }

    // Populate template with content/data -- content/data can be replaced with relevant data from database or API
    const populateTemplate = (data, target) => {
        data.forEach((d, index) => {
            // Clone template node
            const newTemplate = template.cloneNode(true);

            // Stock badges
            const lowStock = `<div class="badge badge-light-warning">Low Stock</div>`;
            const inStock = `<div class="badge badge-light-success">In Stock</div>`;

            // Select data elements
            const image = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_image"]');
            const name = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_name"]');
            const description = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_description"]');
            const cost = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_cost"]');
            const qty = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_qty"]');
            const total = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_total"]');
            const stock = newTemplate.querySelector('[data-kt-docs-datatable-subtable="template_stock"]');

            // Populate elements with data
            const imageSrc = image.getAttribute('src');
            image.setAttribute('src', imageSrc + d.image + '.gif');
            name.innerText = d.name;
            description.innerText = d.description;
            cost.innerText = d.cost;
            qty.innerText = d.qty;
            total.innerText = d.total;
            if (d.stock > 10) {
                stock.innerHTML = inStock;
            } else {
                stock.innerHTML = lowStock;
            }

            // New template border controller
            // When only 1 row is available
            if (data.length === 1) {
                let borderClasses = ['rounded', 'rounded-end-0'];
                newTemplate.querySelectorAll('td')[0].classList.add(...borderClasses);
                borderClasses = ['rounded', 'rounded-start-0'];
                newTemplate.querySelectorAll('td')[4].classList.add(...borderClasses);

                // Remove bottom border
                newTemplate.classList.add('border-bottom-0');
            } else {
                // When multiple rows detected
                if (index === (data.length - 1)) { // first row
                    let borderClasses = ['rounded-start', 'rounded-bottom-0'];
                    newTemplate.querySelectorAll('td')[0].classList.add(...borderClasses);
                    borderClasses = ['rounded-end', 'rounded-bottom-0'];
                    newTemplate.querySelectorAll('td')[4].classList.add(...borderClasses);
                }
                if (index === 0) { // last row
                    let borderClasses = ['rounded-start', 'rounded-top-0'];
                    newTemplate.querySelectorAll('td')[0].classList.add(...borderClasses);
                    borderClasses = ['rounded-end', 'rounded-top-0'];
                    newTemplate.querySelectorAll('td')[4].classList.add(...borderClasses);

                    // Remove bottom border on last row
                    newTemplate.classList.add('border-bottom-0');
                }
            }

            // Insert new template into table
            const tbody = table.querySelector('tbody');
            tbody.insertBefore(newTemplate, target.nextSibling);
        });
    }

    // Reset subtable
    const resetSubtable = () => {
        const subtables = document.querySelectorAll('[data-kt-docs-datatable-subtable="subtable_template"]');
        subtables.forEach(st => {
            st.parentNode.removeChild(st);
        });

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(r => {
            r.classList.remove('isOpen');
            if (r.querySelector('[data-kt-docs-datatable-subtable="expand_row"]')) {
                r.querySelector('[data-kt-docs-datatable-subtable="expand_row"]').classList.remove('active');
            }
        });
    }

    // Public methods
    return {
        init: function () {
            table = document.querySelector('#kt_docs_datatable_subtable');

            if (!table) {
                return;
            }

            initDatatable();
            handleActionButton();
        }
    }
}();

// Webpack support
if (true) {
    module.exports = KTDocsDatatableSubtable;
}

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTDocsDatatableSubtable.init();
});


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = __webpack_require__("../demo8/src/js/custom/documentation/general/datatables/subtable.js");
/******/ 	
/******/ })()
;
//# sourceMappingURL=subtable.js.map