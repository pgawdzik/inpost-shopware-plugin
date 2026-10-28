import Plugin from 'src/plugin-system/plugin.class';

export default class CluewareInpostPlugin extends Plugin {
    init() {
        this.options = JSON.parse(this.el.dataset.cluewareInpostOptions || '{}');
        this.openButton = this.el.querySelector('[data-clueware-inpost-open]');
        this.closeButton = this.el.querySelector('[data-clueware-inpost-close]');
        this.widgetContainer = this.el.querySelector('[data-clueware-inpost-widget-container]');
        this.widgetHost = this.el.querySelector('[data-clueware-inpost-widget]');
        this.selectionText = this.el.querySelector('[data-clueware-inpost-selection-text]');
        this.error = this.el.querySelector('[data-clueware-inpost-error]');
        this.shippingInputs = document.querySelectorAll('input[name="shippingMethodId"]');
        this.selection = this.options.selection || null;
        this.widget = null;
        this.widgetApi = null;
        this.initialMapPositionPromise = null;
        this.widgetReadyPromise = null;

        this.openButton?.addEventListener('click', () => this.openWidget());
        this.closeButton?.addEventListener('click', () => this.closeWidget());
        this.shippingInputs.forEach((input) => input.addEventListener('change', () => this.onShippingChanged()));

        this.onShippingChanged();
    }

    currentShippingMethod() {
        return document.querySelector('input[name="shippingMethodId"]:checked')?.value || '';
    }

    onShippingChanged() {
        const isLocker = this.currentShippingMethod() === this.options.lockerShippingMethodId;
        this.el.classList.toggle('d-none', !isLocker);

        if (!isLocker) {
            this.closeWidget();
            this.hideError();
            if (this.selection) {
                this.selection = null;
                this.clearSelection();
            }
        }
    }

    async openWidget() {
        if (!this.options.token) {
            this.showError();
            return;
        }

        this.showWidgetLoader();

        try {
            const [, initialMapPosition] = await Promise.all([
                this.loadAssets(),
                this.resolveInitialMapPosition(),
            ]);
            await this.mountWidget(initialMapPosition);
            this.hideWidgetLoader();
        } catch (error) {
            this.closeWidget();
            this.showError();
        }
    }

    closeWidget() {
        this.widgetContainer?.classList.add('d-none');
        this.widgetContainer?.classList.remove('is-loading');
        this.widgetContainer?.setAttribute('aria-busy', 'false');
        document.body.classList.remove('clueware-inpost-map-open');
    }

    showWidgetLoader() {
        this.widgetContainer?.classList.add('is-loading');
        this.widgetContainer?.classList.remove('d-none');
        this.widgetContainer?.setAttribute('aria-busy', 'true');
        document.body.classList.add('clueware-inpost-map-open');
    }

    hideWidgetLoader() {
        this.widgetContainer?.classList.remove('is-loading');
        this.widgetContainer?.setAttribute('aria-busy', 'false');
    }

    loadAssets() {
        if (window.customElements?.get('inpost-geowidget')) {
            return Promise.resolve();
        }

        const base = String(this.options.assetsUrl || '').replace(/\/$/, '');
        if (!base.startsWith('https://')) {
            return Promise.reject(new Error('Invalid Geowidget URL'));
        }

        if (!document.querySelector('link[data-clueware-inpost-geowidget]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = `${base}/inpost-geowidget.css`;
            link.dataset.cluewareInpostGeowidget = 'true';
            document.head.appendChild(link);
        }

        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-clueware-inpost-geowidget]');
            if (existing) {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', reject, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = `${base}/inpost-geowidget.js`;
            script.async = true;
            script.dataset.cluewareInpostGeowidget = 'true';
            script.addEventListener('load', () => {
                script.dataset.cluewareInpostGeowidgetLoaded = 'true';
                resolve();
            }, { once: true });
            script.addEventListener('error', reject, { once: true });
            document.head.appendChild(script);
        }).then(() => window.customElements?.whenDefined('inpost-geowidget'));
    }

    mountWidget(initialMapPosition) {
        if (this.widgetReadyPromise) {
            return this.widgetReadyPromise;
        }

        this.widgetReadyPromise = new Promise((resolve, reject) => {
            const timeout = window.setTimeout(() => reject(new Error('GeoWidget initialization timeout')), 10000);

            this.widget = document.createElement('inpost-geowidget');
            this.widget.setAttribute('token', this.options.token);
            this.widget.setAttribute('language', this.currentLanguage());
            this.widget.setAttribute('config', 'parcelCollect');
            const countries = this.normalizedCountries();
            if (countries.length) {
                this.widget.setAttribute('country', countries.join(','));
            }
            this.widget.addEventListener('inpost.geowidget.init', async (event) => {
                window.clearTimeout(timeout);
                this.widgetApi = event.detail?.api || null;
                this.widgetApi?.addPointSelectedCallback((point) => this.selectPoint(point));
                await this.applyCurrentLanguage();
                await this.applyInitialMapPosition(initialMapPosition);
                resolve();
            }, { once: true });
            this.widgetHost.appendChild(this.widget);
        });

        return this.widgetReadyPromise;
    }

    currentLanguage() {
        const supported = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pl', 'pt', 'uk'];
        const configured = String(this.options.language || '').trim().toLowerCase();
        if (supported.includes(configured)) {
            return configured;
        }

        const documentLanguage = String(document.documentElement.lang || '')
            .slice(0, 2)
            .toLowerCase();

        return supported.includes(documentLanguage) ? documentLanguage : 'en';
    }

    async applyCurrentLanguage() {
        if (typeof this.widgetApi?.changeLanguage !== 'function') {
            return;
        }

        try {
            await Promise.resolve(this.widgetApi.changeLanguage(this.currentLanguage()));
            await new Promise((resolve) => window.setTimeout(resolve, 100));
        } catch (error) {
            // The language attribute remains the fallback when the API call fails.
        }
    }

    normalizedCountries() {
        if (!Array.isArray(this.options.countries)) {
            return [];
        }

        return this.options.countries
            .filter((country) => typeof country === 'string' && /^[A-Z]{2}$/.test(country));
    }

    resolveInitialMapPosition() {
        if (this.initialMapPositionPromise) {
            return this.initialMapPositionPromise;
        }

        this.initialMapPositionPromise = this.findInitialMapPosition();

        return this.initialMapPositionPromise;
    }

    async findInitialMapPosition() {
        const location = this.normalizedAddressLocation();
        if (!location) {
            return null;
        }

        for (const candidate of this.addressSearchCandidates(location)) {
            const point = await this.findReferencePoint(candidate.query, location.countryCode);
            if (!point) {
                continue;
            }

            return { location: point.location, zoom: candidate.zoom };
        }

        return null;
    }

    async applyInitialMapPosition(initialMapPosition) {
        if (!initialMapPosition || typeof this.widgetApi?.changePosition !== 'function') {
            return;
        }

        try {
            await Promise.resolve(this.widgetApi.changePosition(
                initialMapPosition.location,
                initialMapPosition.zoom,
            ));
            await new Promise((resolve) => window.setTimeout(resolve, 350));
        } catch (error) {
            // GeoWidget keeps its configured default position when repositioning fails.
        }
    }

    normalizedAddressLocation() {
        const location = this.options.shippingAddressLocation;
        if (!location || typeof location !== 'object') {
            return null;
        }

        const normalized = {
            street: this.normalizeSearchText(location.street, 120),
            postalCode: this.normalizeSearchText(location.postalCode, 16),
            city: this.normalizeSearchText(location.city, 100),
            countryCode: String(location.countryCode || '').trim().toUpperCase(),
        };

        if (normalized.postalCode.length < 3
            || normalized.city.length < 2
            || !this.normalizedCountries().includes(normalized.countryCode)) {
            return null;
        }

        return normalized;
    }

    addressSearchCandidates(location) {
        let street = location.street
            .replace(/[,\s]+\d+[\p{L}]?(?:[-/]\d+[\p{L}]?)?\s*$/u, '')
            .trim();

        if (location.countryCode === 'GB') {
            street = street.replace(/^\d+[\p{L}]?(?:[-/]\d+[\p{L}]?)?\s+/u, '').trim();
        }

        const candidates = [
            { query: [street, location.postalCode].filter(Boolean).join(' '), zoom: 15 },
            { query: location.postalCode, zoom: 14 },
            { query: location.city, zoom: 12 },
        ];
        const queries = new Set();

        return candidates.filter(({ query }) => {
            if (query.length < 3 || queries.has(query)) {
                return false;
            }

            queries.add(query);

            return true;
        });
    }

    normalizeSearchText(value, limit) {
        return String(value || '')
            .replace(/[\u0000-\u001F\u007F]+/gu, ' ')
            .replace(/\s+/gu, ' ')
            .trim()
            .slice(0, limit);
    }

    async findReferencePoint(query, countryCode) {
        const endpoint = String(this.options.pointsApiUrl || '').trim();
        if (!endpoint.startsWith('https://') || !this.options.token) {
            return null;
        }

        let url;
        try {
            url = new URL(endpoint);
        } catch (error) {
            return null;
        }

        url.search = new URLSearchParams({
            query,
            status: 'Operating,NonOperating',
            functions: 'parcel_collect',
            perPage: '3',
            page: '1',
            type: 'parcel_locker,pop',
            country: countryCode,
        }).toString();

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 4000);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'omit',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.options.token}`,
                    'app-referrer': window.location.host,
                },
            });
            if (!response.ok) {
                return null;
            }

            const result = await response.json();
            if (!Array.isArray(result?.items)) {
                return null;
            }

            const point = result.items.find((item) => this.hasValidLocation(item?.location));
            if (!point) {
                return null;
            }

            return {
                location: {
                    latitude: Number(point.location.latitude),
                    longitude: Number(point.location.longitude),
                },
            };
        } catch (error) {
            return null;
        } finally {
            window.clearTimeout(timeout);
        }
    }

    hasValidLocation(location) {
        const latitude = Number(location?.latitude);
        const longitude = Number(location?.longitude);

        return Number.isFinite(latitude)
            && latitude >= -90
            && latitude <= 90
            && Number.isFinite(longitude)
            && longitude >= -180
            && longitude <= 180;
    }

    async selectPoint(point) {
        try {
            const response = await fetch(this.options.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(point),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error('Point could not be saved');
            }

            this.selection = result.selection;
            this.selectionText.textContent = this.formatSelection(result.selection);
            this.hideError();
            this.closeWidget();
        } catch (error) {
            this.showError();
        }
    }

    async clearSelection() {
        try {
            await fetch(this.options.clearUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
        } catch (error) {
            // Server-side validation still prevents an invalid order.
        }
    }

    showError() {
        this.error?.classList.remove('d-none');
    }

    hideError() {
        this.error?.classList.add('d-none');
    }

    formatSelection(selection) {
        const address = [
            [selection.street, selection.buildingNumber].filter(Boolean).join(' '),
            [selection.postCode, selection.city].filter(Boolean).join(' '),
        ].filter(Boolean).join(', ');

        return `${selection.name}${address ? ` — ${address}` : ''}`;
    }
}
