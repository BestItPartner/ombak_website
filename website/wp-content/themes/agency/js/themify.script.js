;// Themify Theme Scripts - http://themify.me/

(function($){

// Fixed Header /////////////////////////////////////////////
var FixedHeader = {
	init: function() {
		if( $('#headerwrap').length < 1 ) return;
		this.headerHeight = $('#headerwrap').outerHeight();
		$(window).on('scroll', this.activate);
		$(window).on('touchstart.touchScroll', this.activate);
		$(window).on('touchmove.touchScroll', this.activate);
	},

	activate: function() {
		var $window = $(window),
			scrollTop = $window.scrollTop(),
			offsetTop = Math.max($('#main-nav-wrap').offset().top, 0);
		
		if ( scrollTop > offsetTop ) {
			FixedHeader.scrollEnabled();
		} else if ( scrollTop + $window.height() == $window.height() ) {
			FixedHeader.scrollDisabled();
		}
	},

	scrollDisabled: function() {
		$('#headerwrap').removeClass("fixed-header");
		$('#header').removeClass('header-on-scroll');
		$('#social-wrap').show();
		$('#pagewrap').css('padding-top', '');
		$('body').removeClass('fixed-header-on');
	},

	scrollEnabled: function() {
		$('#headerwrap').addClass("fixed-header");
		$('#header').addClass('header-on-scroll');
		$('#social-wrap').hide();
		$('#pagewrap').css('padding-top', FixedHeader.headerHeight);
		$('body').addClass('fixed-header-on');
	}
};

/////////////////////////////////////////////
// jQuery functions
/////////////////////////////////////////////
$(document).ready(function(){

	// Initialize Fixed Header	///////////////////////
	if(themifyScript.fixedHeader){
            FixedHeader.init();
	}


	/////////////////////////////////////////////
	// Scroll to top 							
	/////////////////////////////////////////////
	$('.back-top a').click(function () {
            $('body,html').animate({scrollTop: 0}, 800);
            return false;
	});
	
	/////////////////////////////////////////////
	// Toggle menu on mobile 							
	/////////////////////////////////////////////
	$("#menu-icon").click(function(){
            $("#headerwrap #main-nav").fadeToggle();
            $(this).toggleClass("active");
	});

	if( $( 'body' ).hasClass( 'touch' ) && typeof jQuery.fn.themifyDropdown != 'function' ) {
		Themify.LoadAsync(themify_vars.url + '/js/themify.dropdown.js', function(){
			$( '#main-nav' ).themifyDropdown();
		});
	}

});

$(window).load(function(){
	
	// expand slider
	$('#slider .slides').css('height','auto');
	 if($('.portfolio .slideshow').length>0){
            if(!$.fn.carouFredSel){
               Themify.LoadAsync(themify_vars.url+'/js/carousel.min.js',ThemifyCarouselInit);
           }
           else{
               ThemifyCarouselInit();
           }
        }
	function ThemifyCarouselInit(){
            $('.portfolio .slideshow').each(function(){
                $this = $(this);
                $this.carouFredSel({
                        responsive: true,
                        pagination: {
                            container: '#' + $this.data('id') + ' .carousel-pager'
                        },
                        circular: true,
                        infinite: true,
                        swipe: true,
                        scroll: {
                            items: 1,
                            fx: $this.data('effect'),
                            duration: parseInt($this.data('speed'))
                        },
                        auto : {
                            play: 'off' != $this.data('autoplay')? true: false,
                            timeoutDuration: 'off' != $this.data('autoplay')? parseInt($this.data('autoplay')): 0
                        },
                        items: {
                                visible: {
                                        min: 1,
                                        max: 1
                                },
                                width: 222
                        },
                        onCreate : function(){
                            $('.slideshow-wrap').css({'visibility':'visible', 'height':'auto'});
                        }
                });
            });
	}
});

}(jQuery));