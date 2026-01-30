jQuery(document).ready(function($) {
    
    class GeoAds {
        constructor() {
            this.container = $('#geo-ads-container');
            if (this.container.length) {
                this.init();
            }
        }
        
        init() {
            this.getUserLocation();
        }
        
        getUserLocation() {
            // First try HTML5 geolocation
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.getZipFromCoords(position.coords.latitude, position.coords.longitude);
                    },
                    (error) => {
                        console.log('Geolocation error:', error);
                        this.fallbackToIPGeolocation();
                    },
                    {
                        timeout: 10000,
                        maximumAge: 600000 // 10 minutes
                    }
                );
            } else {
                this.fallbackToIPGeolocation();
            }
        }
        
        getZipFromCoords(lat, lng) {
            // Use reverse geocoding to get zip code from coordinates
            // This uses a free service - in production, consider using Google Maps API
            $.ajax({
                url: `https://api.zippopotam.us/us/${lat},${lng}`,
                method: 'GET',
                timeout: 5000,
                success: (data) => {
                    if (data && data.places && data.places[0]) {
                        const zipCode = data.places[0]['post code'];
                        this.loadAds(zipCode, lat, lng);
                    } else {
                        this.fallbackToIPGeolocation();
                    }
                },
                error: () => {
                    this.fallbackToIPGeolocation();
                }
            });
        }
        
        fallbackToIPGeolocation() {
            // Try multiple IP geolocation services
            const services = [
                {
                    url: 'https://ipapi.co/json/',
                    zipField: 'postal'
                },
                {
                    url: 'http://ip-api.com/json/',
                    zipField: 'zip'
                },
                {
                    url: 'https://ipinfo.io/json',
                    zipField: 'postal'
                }
            ];
            
            this.tryIPService(services, 0);
        }
        
        tryIPService(services, index) {
            if (index >= services.length) {
                this.showError('Unable to determine your location');
                return;
            }
            
            const service = services[index];
            
            $.ajax({
                url: service.url,
                method: 'GET',
                timeout: 5000,
                success: (data) => {
                    if (data && data[service.zipField]) {
                        const lat = data.lat || data.latitude;
                        const lng = data.lon || data.longitude;
                        this.loadAds(data[service.zipField], lat, lng);
                    } else {
                        this.tryIPService(services, index + 1);
                    }
                },
                error: () => {
                    this.tryIPService(services, index + 1);
                }
            });
        }
        
        loadAds(zipCode, lat = null, lng = null) {
            $.ajax({
                url: geoAds.ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_geo_ads',
                    zip_code: zipCode,
                    lat: lat,
                    lng: lng,
                    nonce: geoAds.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayAds(response.data, zipCode);
                    } else {
                        this.showError('No ads found for your area');
                    }
                },
                error: () => {
                    this.showError('Unable to load ads');
                }
            });
        }
        
        displayAds(ads, zipCode) {
            const container = this.container;
            const limit = parseInt(container.data('limit')) || 5;
            const layout = container.data('layout') || 'list';
            const showImage = container.data('show-image') === 'true';
            const showExcerpt = container.data('show-excerpt') === 'true';
            
            if (!ads || ads.length === 0) {
                this.showError('No ads available for your area');
                return;
            }
            
            // Limit the number of ads
            const displayAds = ads.slice(0, limit);
            
            let html = `<div class="geo-ads-wrapper geo-ads-${layout}">`;
            html += `<div class="geo-ads-header">Ads for ${zipCode}</div>`;
            
            displayAds.forEach((ad) => {
                html += this.renderAd(ad, showImage, showExcerpt);
            });
            
            html += '</div>';
            
            container.html(html);
            
            // Add click tracking
            container.find('.geo-ad-item').on('click', (e) => {
                const adId = $(e.currentTarget).data('ad-id');
                this.trackAdClick(adId, zipCode);
            });
        }
        
        renderAd(ad, showImage, showExcerpt) {
            let html = `<div class="geo-ad-item" data-ad-id="${ad.id}">`;
            
            if (showImage && ad.featured_image) {
                html += `<div class="geo-ad-image">
                    <img src="${ad.featured_image}" alt="${ad.title}" loading="lazy">
                </div>`;
            }
            
            html += `<div class="geo-ad-content">`;
            html += `<h3 class="geo-ad-title"><a href="${ad.permalink}">${ad.title}</a></h3>`;
            
            if (showExcerpt && ad.excerpt) {
                html += `<div class="geo-ad-excerpt">${ad.excerpt}</div>`;
            }
            
            html += `<div class="geo-ad-meta">Zip codes: ${ad.zip_codes}</div>`;
            html += `</div></div>`;
            
            return html;
        }
        
        trackAdClick(adId, zipCode) {
            // Optional: Track ad clicks for analytics
            $.ajax({
                url: geoAds.ajaxurl,
                method: 'POST',
                data: {
                    action: 'track_geo_ad_click',
                    ad_id: adId,
                    zip_code: zipCode,
                    nonce: geoAds.nonce
                }
            });
        }
        
        showError(message) {
            this.container.html(`<div class="geo-ads-error">${message}</div>`);
        }
    }
    
    // Initialize when DOM is ready
    new GeoAds();
    
    // Also provide global function for manual initialization
    window.initGeoAds = function() {
        new GeoAds();
    };
});