;// Themify Theme Scripts - http://themify.me/

// Initialize object literals
var FixedHeader = {};

/////////////////////////////////////////////
// jQuery functions
/////////////////////////////////////////////
(function($){

	// Test if this is a touch device /////////
	function is_touch_device() {
		return $('body').hasClass('touch');
	}

	// Fixed Header /////////////////////////
	FixedHeader = {
		init: function() {
			if( $('#headerwrap').length < 1 ) return;
			this.headerHeight = $('#headerwrap').outerHeight();
			$(window).on('scroll', this.activate);
			$(window).on('touchstart.touchScroll', this.activate);
			$(window).on('touchmove.touchScroll', this.activate);
		},

		activate: function() {
			var $window = $(window),
				scrollTop = $window.scrollTop();
			if ( scrollTop > $('#header').offset().top ) {
				FixedHeader.scrollEnabled();
			} else if ( scrollTop + $window.height() == $window.height() ) {
				FixedHeader.scrollDisabled();
			}
		},

		scrollDisabled: function() {
			$('#headerwrap').removeClass('fixed-header');
			$('#header').removeClass('header-on-scroll');
			$('#pagewrap').css('padding-top', '');
			$('body').removeClass('fixed-header-on');
		},

		scrollEnabled: function() {
			$('#headerwrap').addClass('fixed-header');
			$('#header').addClass('header-on-scroll');
			$('#pagewrap').css('padding-top', $('#headerwrap').outerHeight());
			$('body').addClass('fixed-header-on');
		}
	};

	// Initialize carousels //////////////////////////////
	function createCarousel(obj) {
		obj.each(function() {
			var $this = $(this);
			$this.carouFredSel({
				responsive : true,
				prev : '#' + $this.data('previd'),
				next : '#' + $this.data('nextid'),
				circular : true,
				infinite : true,
				swipe: true,
				scroll : {
					items : 1,
					fx : $this.data('effect'),
					duration : parseInt($this.data('speed'))
				},
				auto : {
					play : !!('off' != $this.data('autoplay')),
					timeoutDuration : 'off' != $this.data('autoplay') ? parseInt($this.data('autoplay')) : 0,
					button: '#breaking-news-play_pause'
				},
				items : {
					visible : {
						min : 1,
						max : 1
					},
					width : 222
				},
				onCreate : function() {
					$this.closest('.slideshow-wrap').css({
						'visibility' : 'visible',
						'height' : 'auto'
					});
					$('.breaking-news .carousel-nav-wrap').remove();
					$('.breaking-news-loader').remove();
					$(window).resize();
				}
			});
		});
	}

	// DOCUMENT READY
	$(document).ready(function() {

		// Fixed header
		if(themifyScript.fixedHeader){
			FixedHeader.init();
		}

		/////////////////////////////////////////////
		// Scroll to top
		/////////////////////////////////////////////
		$('.back-top a').click(function() {
			$('body,html').animate({ scrollTop: 0 }, 800);
			return false;
		});

		/////////////////////////////////////////////
		// Add class if menu has children
		/////////////////////////////////////////////
		$("#main-nav li:has(ul)").addClass("has-sub-menu");
		$("#main-nav li:has(div)").addClass("has-mega-sub-menu");

		if( $( 'body' ).hasClass( 'touch' ) && typeof jQuery.fn.themifyDropdown != 'function' ) {
			Themify.LoadAsync(themify_vars.url + '/js/themify.dropdown.js', function(){
				$( '#main-nav' ).themifyDropdown();
				$( '#top-nav' ).themifyDropdown();
			});
		}

		/////////////////////////////////////////////
		// Add class for longer meta info
		/////////////////////////////////////////////
		$('.post-content .post-title + .post-meta span').parent(".post-meta").addClass("post-meta-details");

		/////////////////////////////////////////////
		// search icon add class if it's focused
		/////////////////////////////////////////////
		$(function () {
			$("#headerwrap #searchform :input").focus(function() {
				$(".icon-search").addClass("icon-focus");
			}).blur(function() {
				$(".icon-search").removeClass("icon-focus");
			});
		});

		if ( $('.has-mega-sub-menu').length ) {
            Themify.LoadAsync(themifyScript.themeURI + '/themify/megamenu/js/themify.mega-menu.js', null,
				null,
				null,
				function () {
					return ('undefined' !== typeof $.fn.ThemifyMegaMenu);
            });
        }

		/////////////////////////////////////////////
		// Toggle nav on mobile
		/////////////////////////////////////////////
		var $menuIcon = $('#menu-icon'),
			$menuIconTop = $('#menu-icon-top'),
			$topNav = $('#top-nav'),
			$mainNav = $('#main-nav');

		$('#menu-icon').themifySideMenu();
		$('#menu-icon-top').themifySideMenu({
			panel: '#top-nav-mobile-menu',
			side: 'left'
		});
                var $body = $('body'),
                $overlay = $( '<div class="body-overlay">' );
                $body.append( $overlay ).on( 'sidemenushow.themify', function () {
                    $overlay.addClass( 'body-overlay-on' );
                } ).on( 'sidemenuhide.themify', function () {
                    $overlay.removeClass( 'body-overlay-on' );
                } ).on( 'click.themify touchend.themify', '.body-overlay', function () {
                    $( '#menu-icon' ).themifySideMenu( 'hide' );
					$( '#menu-icon-top' ).themifySideMenu( 'hide' );
                } );
                // Reset slide nav width
		$(window).resize(function(){
                    if ($(window).width() > 780) {
                        $('body').removeAttr('style');
                    }
                    if( $( '#menu-icon' ).is(':visible') && $('#mobile-menu').hasClass('sidemenu-on')){
                          $overlay.addClass( 'body-overlay-on' );
                    }
                    else{
                         $overlay.removeClass( 'body-overlay-on' );
                    }
		});

		/////////////////////////////////////////////
		// NiceScroll plugin
		/////////////////////////////////////////////
		if ( typeof $.fn.niceScroll !== 'undefined' && ! is_touch_device() ) {
			var event = 'click';
			$menuIcon.on(event, function(){
				var nice = $mainNav.getNiceScroll();
				if ( nice.length == 0 ) {
					$mainNav.niceScroll({
						autohidemode: false
					});
				}
			});
			$('.mega').each(function(){
				var alink = $(this).find('a').first(),
                                    vw = $(window).width();
				$(window).resize(function(){
					vw = $(window).width();
				});
				alink.hover(function(){
					var niceMega = $(".mega .mega-sub-menu ul").getNiceScroll();
					if(niceMega.length == 0 && vw > 780) {
						$(".mega .mega-sub-menu ul").show().niceScroll({
							autohidemode: false
						});
					}
				});
			});
			// Menu Top
			$menuIconTop.on(event, function(){
				var nice = $topNav.getNiceScroll();
				if ( nice.length == 0 ) {
					$topNav.niceScroll({
						autohidemode: false
					});
				}
			});
		}

		/////////////////////////////////////////////
		// Header height for slide effect
		/////////////////////////////////////////////
		function headerHeightSlide(){
			var headerH = $('#headerwrap').height();
			if (headerH > 120) {
				$("#headerwrap").addClass("long-menu");
			} else {
				$("#headerwrap").removeClass("long-menu");
			}
		}
		headerHeightSlide();
		$(window).resize(headerHeightSlide);

	});

	// WINDOW LOAD
	$(window).load(function() {

		/////////////////////////////////////////////
		// Carousel initialization
		/////////////////////////////////////////////
                if($('.slideshow').length>0){
                    if(!$.fn.carouFredSel){
                        Themify.LoadAsync(themify_vars.url+'/js/carousel.min.js',function(){
                            createCarousel( $('.slideshow') );
                        });
                    }
                    else{
                       createCarousel( $('.slideshow') );
                    }
                }

		/////////////////////////////////////////////
		// Pause breaking news
		/////////////////////////////////////////////
		$('#breaking-news-play_pause').click(function(){
			$(this).toggleClass('playing');
		});

		// EDGE MENU //
		$(function ($) {
			$("#main-nav li").on('mouseenter mouseleave dropdown_open', function (e) {
				if ($('ul', this).length) {
					var elm = $('ul:first', this);
					var off = elm.offset();
					var l = off.left;
					var w = elm.width();
					var docW = $(window).width();
					var isEntirelyVisible = (l + w <= docW);

					if (!isEntirelyVisible) {
						$(this).addClass('edge');
						console.log('isEntirelyVisible');
					} else {
						$(this).removeClass('edge');
					}

				}
			});
		});


	});

})(jQuery);
