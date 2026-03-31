addEventListener("DOMContentLoaded", (event) => {

    if (window.location.pathname.includes('deal')) {
        BX.ajax.runAction('nebo:map.api.Filters.filterCategory', {})
            .then(function (response) {
                window.categoryID = response.data;
            });


        const elBlock = document.querySelector(".ui-nav-panel.ui-nav-panel__scope")
        const elMap = BX.create(
            'div',
            {
                attrs: {
                    className: 'ui-nav-panel__item',
                    id: 'ui-nav-panel-item-map',
                },
                events: {
                    click: () => {
                        window.location.replace("/crm/deal/map/category/" + window.categoryID + "/");
                    }
                },
                text: 'Карта'
            }
        );

        BX.append(elMap, elBlock)
    }

})