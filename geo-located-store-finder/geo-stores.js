jQuery(document).ready(function($) {
    
    class GeoStoreLocator {
        constructor() {
            this.container = $('#nearby-stores-container');
            this.userLocation = null;
            this.stores = [];
            
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
                        this.userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        this.loadNearbyStores();
                    },
                    (error) => {
                        console.log('Geolocation error:', error);
                        this.fallbackToIPGeolocation();
                    },
                    {
                        timeout: 10000,
                        maximumAge: 600000, // 10 minutes
                        enableHighAccuracy: true
                    }
                );
            } else {
                this.fallbackToIPGeolocation();
            }
        }
        
        fallbackToIPGeolocation() {
            // Try multiple IP geolocation services
            const services = [
                {
                    url: 'https://ipapi.co/json/',
                    latField: 'latitude',
                    lngField: 'longitude'
                },
                {
                    url: 'http://ip-api.com/json/',
                    latField: 'lat',
                    lngField: 'lon'
                },
                {
                    url: 'https://ipinfo.io/json',
                    latField: 'loc',
                    lngField: 'loc',
                    parseLocation: true
                }
            ];
            
            this.tryIPService(services, 0);
        }
        
        tryIPService(services, index) {
            if (index >= services.length) {
                this.showError('Unable to determine your location. Please enable location services or try again.');
                return;
            }
            
            const service = services[index];
            
            $.ajax({
                url: service.url,
                method: 'GET',
                timeout: 5000,
                success: (data) => {
                    let lat, lng;
                    
                    if (service.parseLocation && data.loc) {
                        // ipinfo.io returns "lat,lng" format
                        const coords = data.loc.split(',');
                        lat = parseFloat(coords[0]);
                        lng = parseFloat(coords[1]);
                    } else {
                        lat = data[service.latField];
                        lng = data[service.lngField];
                    }
                    
                    if (lat && lng) {
                        this.userLocation = { lat: lat, lng: lng };
                        this.loadNearbyStores();
                    } else {
                        this.tryIPService(services, index + 1);
                    }
                },
                error: () => {
                    this.tryIPService(services, index + 1);
                }
            });
        }
        
        loadNearbyStores() {
            if (!this.userLocation) {
                this.showError('Location not available');
                return;
            }
            
            const container = this.container;
            const limit = parseInt(container.data('limit')) || 10;
            const radius = parseFloat(container.data('radius')) || 25;
            
            $.ajax({
                url: geoStores.ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_nearby_stores',
                    lat: this.userLocation.lat,
                    lng: this.userLocation.lng,
                    limit: limit,
                    radius: radius,
                    nonce: geoStores.nonce
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        this.stores = response.data;
                        this.displayStores();
                    } else {
                        this.showError('No stores found within ' + radius + ' miles of your location.');
                    }
                },
                error: () => {
                    this.showError('Unable to load nearby stores. Please try again.');
                }
            });
        }
        
        displayStores() {
            const container = this.container;
            const layout = container.data('layout') || 'list';
            const showImage = container.data('show-image') === 'true';
            const showDistance = container.data('show-distance') === 'true';
            const showAddress = container.data('show-address') === 'true';
            const showMap = container.data('show-map') === 'true';
            const unit = container.data('unit') || 'miles';
            
            let html = `<div class="geo-stores-wrapper geo-stores-${layout}">`;
            
            // Add header with location info
            html += `<div class="geo-stores-header">
                <h3>Stores Near You</h3>
                <p class="user-location-info">Found ${this.stores.length} stores nearby</p>
            </div>`;
            
            // Add map if requested
            if (showMap) {
                html += this.renderMap();
            }
            
            // Add stores list
            html += '<div class="stores-list">';
            this.stores.forEach((store) => {
                html += this.renderStore(store, showImage, showDistance, showAddress, unit);
            });
            html += '</div>';
            
            html += '</div>';
            
            container.html(html);
            
            // Initialize map if requested
            if (showMap) {
                this.initializeMap();
            }
            
            // Add click tracking
            container.find('.geo-store-item').on('click', (e) => {
                const storeId = $(e.currentTarget).data('store-id');
                this.trackStoreClick(storeId);
            });
            
            // Add directions links
            container.find('.get-directions').on('click', (e) => {
                e.preventDefault();
                const lat = $(e.currentTarget).data('lat');
                const lng = $(e.currentTarget).data('lng');
                this.openDirections(lat, lng);
            });
        }
        
        renderStore(store, showImage, showDistance, showAddress, unit) {
            let html = `<div class="geo-store-item" data-store-id="${store.id}">`;
            
            // Store image
            if (showImage && store.featured_image) {
                html += `<div class="geo-store-image">
                    <img src="${store.featured_image}" alt="${store.title}" loading="lazy">
                </div>`;
            }
            
            html += `<div class="geo-store-content">`;
            
            // Store title and distance
            html += `<div class="geo-store-header">
                <h4 class="geo-store-title">
                    <a href="${store.permalink}">${store.title}</a>
                </h4>`;
            
            if (showDistance) {
                const distanceUnit = unit === 'km' ? 'km' : 'mi';
                const distance = unit === 'km' ? (store.distance * 1.609).toFixed(1) : store.distance;
                html += `<span class="geo-store-distance">${distance} ${distanceUnit}</span>`;
            }
            
            html += `</div>`;
            
            // Store address
            if (showAddress && store.address) {
                html += `<div class="geo-store-address">
                    <span class="address-icon">📍</span>
                    ${store.address}
                </div>`;
            }
            
            // Store contact info
            let contactInfo = [];
            if (store.phone) {
                contactInfo.push(`<a href="tel:${store.phone}" class="store-phone">📞 ${store.phone}</a>`);
            }
            if (store.website) {
                contactInfo.push(`<a href="${store.website}" target="_blank" class="store-website">🌐 Website</a>`);
            }
            
            if (contactInfo.length > 0) {
                html += `<div class="geo-store-contact">${contactInfo.join(' | ')}</div>`;
            }
            
            // Store hours
            if (store.hours) {
                html += `<div class="geo-store-hours">
                    <strong>Hours:</strong> ${store.hours.replace(/\n/g, '<br>')}
                </div>`;
            }
            
            // Store excerpt
            if (store.excerpt) {
                html += `<div class="geo-store-excerpt">${store.excerpt}</div>`;
            }
            
            // Action buttons
            html += `<div class="geo-store-actions">
                <a href="${store.permalink}" class="button store-details">View Details</a>
                <a href="#" class="button get-directions" data-lat="${store.latitude}" data-lng="${store.longitude}">Get Directions</a>
            </div>`;
            
            html += `</div></div>`;
            
            return html;
        }
        
        renderMap() {
            return `<div class="geo-stores-map">
                <div id="stores-map" style="width: 100%; height: 400px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                        📍 Interactive Map
                        <br><small>Map functionality requires additional implementation</small>
                    </div>
                </div>
            </div>`;
        }
        
        initializeMap() {
            // This is a placeholder for map initialization
            // You would implement this with Google Maps, Leaflet, or another mapping library
            console.log('Map initialization would go here');
            console.log('User location:', this.userLocation);
            console.log('Stores:', this.stores);
        }
        
        openDirections(lat, lng) {
            if (!this.userLocation) {
                alert('Your location is not available for directions.');
                return;
            }
            
            // Open directions in Google Maps
            const url = `https://www.google.com/maps/dir/${this.userLocation.lat},${this.userLocation.lng}/${lat},${lng}`;
            window.open(url, '_blank');
        }
        
        trackStoreClick(storeId) {
            // Optional: Track store clicks for analytics
            $.ajax({
                url: geoStores.ajaxurl,
                method: 'POST',
                data: {
                    action: 'track_store_click',
                    store_id: storeId,
                    user_lat: this.userLocation ? this.userLocation.lat : null,
                    user_lng: this.userLocation ? this.userLocation.lng : null,
                    nonce: geoStores.nonce
                }
            });
        }
        
        showError(message) {
            this.container.html(`<div class="geo-stores-error">${message}</div>`);
        }
        
        // Public method to refresh stores (useful for AJAX-loaded content)
        refresh() {
            if (this.userLocation) {
                this.loadNearbyStores();
            } else {
                this.getUserLocation();
            }
        }
        
        // Public method to search by specific location
        searchByAddress(address) {
            // Geocode the address and then search for stores
            this.geocodeAddress(address, (coordinates) => {
                if (coordinates) {
                    this.userLocation = coordinates;
                    this.loadNearbyStores();
                } else {
                    this.showError('Could not find the specified address.');
                }
            });
        }
        
        geocodeAddress(address, callback) {
            // Simple geocoding using a free service
            $.ajax({
                url: 'https://nominatim.openstreetmap.org/search',
                data: {
                    q: address,
                    format: 'json',
                    limit: 1
                },
                success: (data) => {
                    if (data && data.length > 0) {
                        callback({
                            lat: parseFloat(data[0].lat),
                            lng: parseFloat(data[0].lon)
                        });
                    } else {
                        callback(null);
                    }
                },
                error: () => {
                    callback(null);
                }
            });
        }
    }
    
    // Initialize when DOM is ready
    window.storeLocator = new GeoStoreLocator();
    
    // Provide global functions
    window.initStoreLocator = function() {
        window.storeLocator = new GeoStoreLocator();
    };
    
    window.refreshStores = function() {
        if (window.storeLocator) {
            window.storeLocator.refresh();
        }
    };
    
    window.searchStoresByAddress = function(address) {
        if (window.storeLocator) {
            window.storeLocator.searchByAddress(address);
        }
    };
});

// Add address search functionality if there's a search form
jQuery(document).ready(function($) {
    // Look for address search form
    $(document).on('submit', '.store-address-search', function(e) {
        e.preventDefault();
        const address = $(this).find('input[name="search_address"]').val();
        if (address && window.searchStoresByAddress) {
            window.searchStoresByAddress(address);
        }
    });
    
    // Add search form helper function
    window.addStoreSearchForm = function(containerId) {
        const container = $('#' + containerId);
        if (container.length) {
            const searchForm = `
                <form class="store-address-search" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="text" name="search_address" placeholder="Enter address, city, or zip code" 
                               style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="submit" style="padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Search Stores
                        </button>
                        <button type="button" id="use-my-location" style="padding: 8px 16px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Use My Location
                        </button>
                    </div>
                </form>
            `;
            container.prepend(searchForm);
            
            // Add click handler for "Use My Location" button
            $('#use-my-location').on('click', function() {
                if (window.refreshStores) {
                    window.refreshStores();
                }
            });
        }
    };
});