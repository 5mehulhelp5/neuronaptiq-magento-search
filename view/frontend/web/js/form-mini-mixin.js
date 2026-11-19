define([
    'jquery'
], function ($) {
    'use strict';

    return function (targetWidget) {
        $.widget('mage.quickSearch', targetWidget, {
            options: {
                // Same as the core template; image, price, breadcrumb (categories) and data-url are only rendered
                // for enhanced suggestions. price is HTML (escaped on the server, observers of the item event can
                // replace it with their own HTML), like in the Smile ElasticSuite templates
                template:
                    '<li class="<%- data.row_class %>" id="qs-option-<%- data.index %>" role="option"' +
                    '<% if (data.url) { %> data-url="<%- data.url %>"<% } %>>' +
                    '<% if (data.image) { %>' +
                    '<img class="qs-option-image" src="<%- data.image %>" alt="" loading="lazy" ' +
                    'width="50" height="50">' +
                    '<% } %>' +
                    '<span class="qs-option-name">' +
                    ' <%- data.title %>' +
                    '<% if (data.breadcrumb && data.breadcrumb.length) { %>' +
                    '<span class="qs-option-breadcrumb"><%- data.breadcrumb.join(\' › \') %></span>' +
                    '<% } %>' +
                    '</span>' +
                    '<% if (data.price) { %>' +
                    '<span class="qs-option-price"><%= data.price %></span>' +
                    '<% } %>' +
                    '<span aria-hidden="true" class="amount">' +
                    '<%- data.num_results %>' +
                    '</span>' +
                    '</li>'
            },

            /**
             * Handles submit: If selected item has a product URL, navigate to product directly
             */
            _onSubmit: function (e) {
                if (this.responseList.selected) {
                    var productUrl = this.responseList.selected.attr('data-url');
                    if (productUrl && productUrl !== 'undefined' && productUrl !== '') {
                        e.preventDefault();
                        window.location.href = productUrl;
                        return false;
                    }
                }
                return this._super(e);
            }
        });

        return $.mage.quickSearch;
    };
});
