(function ($, window, document) {
  'use strict';

  var config = window.useupMeAjaxShopFilter || {};
  var request = null;
  var requestToken = 0;
  var loadMoreObserver = null;
  var isLoadingMore = false;  

  function getFilterBar() {
    return document.querySelector('[data-useup-shop-filter="1"]');
  }

  function getResultsWrapper() {
    return document.querySelector('[data-useup-shop-results="1"]');
  }

  function getOrderingSelect() {
    return document.querySelector('.woocommerce-ordering select');
  }

  function setActiveItem(filterBar, key) {
    filterBar.querySelectorAll('.useup-shop-filter__item').forEach(function (item) {
      var isActive = item.getAttribute('data-filter-key') === key;

      item.classList.toggle('is-active', isActive);

      if (isActive) {
        item.setAttribute('aria-current', 'page');
      } else {
        item.removeAttribute('aria-current');
      }
    });

    filterBar.setAttribute('data-active-key', key || '');
  }

  function getActiveItem(filterBar) {
    return filterBar.querySelector('.useup-shop-filter__item.is-active') || filterBar.querySelector('.useup-shop-filter__item');
  }

  function getCurrentOrderby() {
    var select = getOrderingSelect();

    if (!select) {
      return config.defaultOrderby || 'menu_order';
    }

    return select.value || config.defaultOrderby || 'menu_order';
  }

  function updateHistory(url) {
    if (!window.history || !window.history.pushState || !url) {
      return;
    }

    window.history.pushState({ useupShopFilterUrl: url }, '', url);
  }

  function buildHistoryUrl(baseUrl, orderby, paged) {
    var url;

    if (!baseUrl) {
      return '';
    }

    url = new window.URL(baseUrl, window.location.origin);

    if (orderby && orderby !== (config.defaultOrderby || 'menu_order')) {
      url.searchParams.set('orderby', orderby);
    } else {
      url.searchParams.delete('orderby');
    }

    if (paged && paged > 1) {
      url.searchParams.set('paged', String(paged));
    } else {
      url.searchParams.delete('paged');
    }

    return url.toString();
  }

  function replaceResultCount(html) {
    var currentCount = document.querySelector('.woocommerce-result-count');
    var temp;
    var newCount;

    if (!currentCount || !html) {
      return;
    }

    temp = document.createElement('div');
    temp.innerHTML = html;
    newCount = temp.firstElementChild;

    if (newCount) {
      currentCount.replaceWith(newCount);
    }
  }

  function renderResponse(response, mode) {
    var results = getResultsWrapper();
    var html = '';
    var temp;
    var incomingList;
    var currentList;

    if (!results) {
      return;
    }

    if (mode === 'append') {
      html = response.data.html || '';
      temp = document.createElement('div');
      temp.innerHTML = html;
      incomingList = temp.querySelector('ul.products, .products');
      currentList = results.querySelector('ul.products, .products');

      var oldLoadMore = results.querySelector('.useup-shop-filter__load-more-wrap');

      if (oldLoadMore) {
        oldLoadMore.remove();
      }

      if (incomingList && currentList) {
        Array.prototype.slice.call(incomingList.children).forEach(function (child) {
          currentList.appendChild(child);
        });
      } else if (html) {
        results.insertAdjacentHTML('beforeend', html);
      }

      if (response.data.pagination) {
        results.insertAdjacentHTML('beforeend', response.data.pagination);
      }

    } else {
      results.innerHTML = (response.data.html || '') + (response.data.pagination || '');
    }

    replaceResultCount(response.data.resultCountHtml || '');
    revealAjaxProducts();
    if (
      response.data &&
      response.data.current_page &&
      response.data.max_num_pages &&
      parseInt(response.data.current_page, 10) >= parseInt(response.data.max_num_pages, 10)
    ) {
      var finalLoadMore = results.querySelector('.useup-shop-filter__load-more-wrap');

      if (finalLoadMore) {
        finalLoadMore.remove();
      }

      if (loadMoreObserver) {
        loadMoreObserver.disconnect();
      }
    } else {
      observeInfiniteScroll();
    }

    isLoadingMore = false;
  }

  function setLoading(isLoading) {
    var results = getResultsWrapper();

    if (!results) {
      return;
    }

    results.classList.toggle('useup-shop-products-loading', Boolean(isLoading));
  }

  function buildRequestData(filterBar, options) {
    return {
      action: 'useup_me_filter_shop_products',
      nonce: config.nonce || '',
      filter_key: options.filterKey || filterBar.getAttribute('data-active-key') || '',
      orderby: options.orderby || getCurrentOrderby(),
      paged: options.paged || 1
    };
  }

  function requestProducts(filterBar, options) {
    var requestData = buildRequestData(filterBar, options || {});
    var fallbackUrl = options.fallbackUrl || '';
    var shouldAppend = options.append === true;
    var currentToken;

    if (!config.ajaxUrl || !filterBar) {
      if (fallbackUrl) {
        window.location.href = fallbackUrl;
      }
      return;
    }

    if (request && request.readyState !== 4) {
      request.abort();
    }

    requestToken += 1;
    currentToken = requestToken;

    if (!shouldAppend) {
      setLoading(true);
    }

    request = $.post(config.ajaxUrl, requestData)
      .done(function (response) {
        if (currentToken !== requestToken) {
          return;
        }

        if (!response || response.success !== true || !response.data) {
          if (fallbackUrl) {
            window.location.href = fallbackUrl;
          }
          return;
        }

        setActiveItem(filterBar, requestData.filter_key);
        renderResponse(response, shouldAppend ? 'append' : 'replace');

        if (options.updateUrl !== false) {
          updateHistory(options.historyUrl || fallbackUrl || response.data.item_url || '');
        }
      })
      .fail(function (_jqXHR, textStatus) {
        if (textStatus === 'abort' || currentToken !== requestToken) {
          return;
        }

        if (fallbackUrl) {
          window.location.href = fallbackUrl;
          return;
        }

        window.alert((config.i18n && config.i18n.error) || 'Nao foi possivel atualizar os produtos agora. Tente novamente.');
      })
      .always(function () {
        var currentLoadMore;

        if (currentToken !== requestToken) {
          return;
        }

        if (!shouldAppend) {
          setLoading(false);
        }

        isLoadingMore = false;

        currentLoadMore = document.querySelector('.useup-shop-filter__load-more.is-loading');

        if (currentLoadMore) {
          currentLoadMore.classList.remove('is-loading');
        }
      });
  }

  function getPageFromUrl(url) {
    var parsed = new window.URL(url, window.location.origin);
    var paged = parsed.searchParams.get('paged');

    return paged ? parseInt(paged, 10) || 1 : 1;
  }

  function revealAjaxProducts() {
    document.querySelectorAll('.useup-shop-filter-results .product-inner.animation, .useup-shop-filter-results .animation.fade').forEach(function (item) {
      item.style.opacity = '1';
      item.style.transform = 'none';
      item.style.visibility = 'visible';
      item.style.willChange = 'auto';
      item.classList.remove('fade');
    });
  }

  function observeInfiniteScroll() {
    var filterBar = getFilterBar();
    var loadMore = document.querySelector('.useup-shop-filter__load-more');

    if (!filterBar || !loadMore || !('IntersectionObserver' in window)) {
      return;
    }

    if (loadMoreObserver) {
      loadMoreObserver.disconnect();
    }

    loadMoreObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        var activeItem;
        var href;
        var nextPage;

        if (!entry.isIntersecting || isLoadingMore) {
          return;
        }

        activeItem = getActiveItem(filterBar);
        href = loadMore.getAttribute('href') || '';
        nextPage = parseInt(loadMore.getAttribute('data-next-page') || '2', 10);
        var maxPages = parseInt(loadMore.getAttribute('data-max-pages') || '0', 10);

        if (!href || !nextPage) {
          return;
        }

        if (maxPages && nextPage > maxPages) {
          if (loadMoreObserver) {
            loadMoreObserver.disconnect();
          }

          loadMore.closest('.useup-shop-filter__load-more-wrap')?.remove();
          return;
        }

        isLoadingMore = true;
        loadMore.classList.add('is-loading');

        requestProducts(filterBar, {
          filterKey: activeItem ? activeItem.getAttribute('data-filter-key') || '' : '',
          orderby: getCurrentOrderby(),
          paged: nextPage,
          append: true,
          fallbackUrl: href,
          historyUrl: href,
          updateUrl: false
        });
      });
    }, {
      root: null,
      rootMargin: '450px 0px',
      threshold: 0
    });

    loadMoreObserver.observe(loadMore);
  }  

  function bindEvents() {
    var filterBar = getFilterBar();

    if (!filterBar) {
      return;
    }

    filterBar.addEventListener('click', function (event) {
      var item = event.target.closest('.useup-shop-filter__item');

      if (!item) {
        return;
      }

      event.preventDefault();

      requestProducts(filterBar, {
        filterKey: item.getAttribute('data-filter-key') || '',
        orderby: getCurrentOrderby(),
        paged: 1,
        fallbackUrl: item.getAttribute('href') || '',
        historyUrl: buildHistoryUrl(item.getAttribute('href') || '', getCurrentOrderby(), 1)
      });
    });

    document.addEventListener('change', function (event) {
      var select = event.target;
      var activeItem;

      if (!select.matches('.woocommerce-ordering select')) {
        return;
      }

      activeItem = getActiveItem(filterBar);

      requestProducts(filterBar, {
        filterKey: activeItem ? activeItem.getAttribute('data-filter-key') || '' : '',
        orderby: select.value || getCurrentOrderby(),
        paged: 1,
        fallbackUrl: activeItem ? activeItem.getAttribute('href') || window.location.href : window.location.href,
        historyUrl: buildHistoryUrl(
          activeItem ? activeItem.getAttribute('href') || window.location.href : window.location.href,
          select.value || getCurrentOrderby(),
          1
        )
      });
    });

    document.addEventListener('click', function (event) {
      var pageLink = event.target.closest('.woocommerce-pagination a');
      var loadMore = event.target.closest('.useup-shop-filter__load-more');
      var activeItem;
      var href;

      if (!pageLink && !loadMore) {
        return;
      }

      activeItem = getActiveItem(filterBar);
      href = (pageLink || loadMore).getAttribute('href') || '';

      if (!href) {
        return;
      }

      event.preventDefault();

      requestProducts(filterBar, {
        filterKey: activeItem ? activeItem.getAttribute('data-filter-key') || '' : '',
        orderby: getCurrentOrderby(),
        paged: loadMore ? parseInt(loadMore.getAttribute('data-next-page') || '2', 10) : getPageFromUrl(href),
        append: Boolean(loadMore),
        fallbackUrl: href,
        historyUrl: href,
        updateUrl: !loadMore
      });
    });

    window.addEventListener('popstate', function () {
      if (!window.location.href) {
        return;
      }

      window.location.reload();
    });

    observeInfiniteScroll();
  }

  $(bindEvents);
})(window.jQuery, window, document);
