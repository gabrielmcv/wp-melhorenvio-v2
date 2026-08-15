(function ($, window, document) {
  'use strict';

  var observer = null;
  var resizeObserver = null;

  function getGalleryScope(element) {
    var $element = $(element);
    var $gallery = $element.closest('.woocommerce-product-gallery, .images');

    return $gallery.length ? $gallery : $(document);
  }

  function getGalleryElements() {
    return $('.woocommerce-product-gallery:has(.useup-product-gallery__image--video), .images:has(.useup-product-gallery__image--video)');
  }

  function pauseVideo(video) {
    if (!video) {
      return;
    }

    try {
      video.pause();
    } catch (error) {
      // Ignore pause failures.
    }
  }

  function playVideo(video) {
    var playPromise;

    if (!video) {
      return;
    }

    video.muted = true;
    playPromise = video.play();

    if (playPromise && typeof playPromise.catch === 'function') {
      playPromise.catch(function () {
        // Ignore autoplay restrictions.
      });
    }
  }

  function pauseGalleryVideos($scope, except) {
    ($scope && $scope.length ? $scope : $(document)).find('.useup-product-gallery__video').each(function () {
      if (except && this === except) {
        return;
      }

      pauseVideo(this);
    });
  }

  function isElementVisible(element) {
    var rect;
    var viewHeight;
    var viewWidth;

    if (!element || !element.getBoundingClientRect) {
      return false;
    }

    rect = element.getBoundingClientRect();
    viewHeight = window.innerHeight || document.documentElement.clientHeight;
    viewWidth = window.innerWidth || document.documentElement.clientWidth;

    return rect.bottom > 0 && rect.right > 0 && rect.top < viewHeight && rect.left < viewWidth;
  }

  function isVideoSlideActive(video) {
    var slide = video ? video.closest('.woocommerce-product-gallery__image, li, .slick-slide, .slides > li') : null;

    if (!slide) {
      return false;
    }

    if (slide.classList.contains('clone') || slide.classList.contains('slick-cloned')) {
      return false;
    }

    if (slide.classList.contains('slick-slide') && !slide.classList.contains('slick-active')) {
      return false;
    }

    if (slide.hasAttribute('aria-hidden') && slide.getAttribute('aria-hidden') === 'true') {
      return false;
    }

    if ($(slide).is(':hidden')) {
      return false;
    }

    return isElementVisible(slide);
  }

  function syncGalleryVideosPlayback($scope) {
    var $root = $scope && $scope.length ? $scope : $(document);
    var activeVideo = null;

    $root.find('.useup-product-gallery__video').each(function () {
      if (!activeVideo && isVideoSlideActive(this)) {
        activeVideo = this;
        return;
      }

      pauseVideo(this);
    });

    if (activeVideo) {
      pauseGalleryVideos($root, activeVideo);
      playVideo(activeVideo);
    }
  }

  function getReferenceImageHeight($gallery) {
    var reference = null;
    var height = 0;

    $gallery.find('.woocommerce-product-gallery__image').each(function () {
      var $slide = $(this);
      var $image;

      if ($slide.hasClass('useup-product-gallery__image--video')) {
        return;
      }

      $image = $slide.find('img').not('.useup-product-gallery__video').first();

      if (!$image.length) {
        return;
      }

      if ($image[0].getBoundingClientRect().height > 0) {
        reference = $image[0];
        return false;
      }

      if (!reference) {
        reference = $image[0];
      }
    });

    if (!reference) {
      return 0;
    }

    height = reference.getBoundingClientRect().height || reference.offsetHeight || 0;

    return height > 0 ? Math.round(height) : 0;
  }

  function syncGalleryVideoLayout($scope) {
    var $galleries = $scope && $scope.length ? $scope.filter('.woocommerce-product-gallery, .images').add($scope.find('.woocommerce-product-gallery, .images')) : getGalleryElements();

    $galleries.each(function () {
      var $gallery = $(this);
      var height;

      if (!$gallery.find('.useup-product-gallery__image--video').length) {
        return;
      }

      $gallery.addClass('useup-product-gallery--has-video');
      height = getReferenceImageHeight($gallery);

      if (height > 0) {
        $gallery[0].style.setProperty('--useup-gallery-video-height', height + 'px');
      }
    });
  }

  function bindVideoInteractions() {
    $(document).on('click.useupGalleryVideo', '.useup-product-gallery__video-shell, .useup-product-gallery__video', function (event) {
      var $scope = getGalleryScope(this);
      var video = $(this).closest('.useup-product-gallery__video-shell').find('.useup-product-gallery__video').get(0);

      event.stopPropagation();

      if (!video) {
        return;
      }

      if (video.paused) {
        pauseGalleryVideos($scope, video);
        playVideo(video);
        return;
      }

      pauseVideo(video);
    });

    $(document).on('click.useupGalleryVideo', '.flex-control-thumbs img, .flex-control-nav a, .woocommerce-product-gallery__trigger, .woocommerce-product-gallery__image a, .slick-arrow, .slick-dots button', function () {
      var $scope = getGalleryScope(this);

      window.setTimeout(function () {
        syncGalleryVideosPlayback($scope);
      }, 80);
    });

    $(window).on('scroll.useupGalleryVideo resize.useupGalleryVideo load.useupGalleryVideo', function () {
      syncGalleryVideoLayout($('.single-product'));
      syncGalleryVideosPlayback($('.single-product'));
    });
  }

  function setupVideoObserver() {
    if (!window.MutationObserver || observer) {
      return;
    }

    observer = new window.MutationObserver(function () {
      syncGalleryVideoLayout($('.single-product'));
      syncGalleryVideosPlayback($('.single-product'));
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style', 'aria-hidden']
    });
  }

  function setupResizeObserver() {
    if (!window.ResizeObserver || resizeObserver) {
      return;
    }

    resizeObserver = new window.ResizeObserver(function () {
      syncGalleryVideoLayout($('.single-product'));
    });

    getGalleryElements().each(function () {
      resizeObserver.observe(this);
    });
  }

  function init() {
    bindVideoInteractions();
    setupVideoObserver();
    setupResizeObserver();
    syncGalleryVideoLayout($('.single-product'));
    syncGalleryVideosPlayback($('.single-product'));
  }

  $(init);
})(jQuery, window, document);
