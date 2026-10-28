const ApiService = Shopware.Classes.ApiService;

class CluewareInpostApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'clueware/inpost') {
        super(httpClient, loginService, apiEndpoint);
    }

    getState(orderId) {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/order/${orderId}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    testConnection() {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/connection-test`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    createShipment(orderId, payload) {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/order/${orderId}/shipment`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    refreshShipment(orderId) {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/order/${orderId}/shipment/refresh`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    cancelShipment(orderId) {
        return this.httpClient
            .delete(`/_action/${this.getApiBasePath()}/order/${orderId}/shipment`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    downloadLabel(orderId) {
        return this.httpClient.get(`/_action/${this.getApiBasePath()}/order/${orderId}/shipment/label`, {
            headers: this.getBasicHeaders(),
            responseType: 'blob',
        });
    }
}

Shopware.Application.addServiceProvider('cluewareInpostApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new CluewareInpostApiService(initContainer.httpClient, container.loginService);
});
