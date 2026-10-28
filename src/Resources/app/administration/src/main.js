import './service/clueware-inpost-api.service';
import './component/clueware-inpost-shipment-card';
import './component/clueware-inpost-connection-test';
import './extension/sw-order-detail-general';
import './module/clueware-inpost-shipping';
import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import plPL from './snippet/pl-PL.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('pl-PL', plPL);

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'order',
    key: 'clueware_inpost_shipping',
    roles: {
        viewer: {
            privileges: ['clueware_inpost_shipping.viewer'],
            dependencies: [],
        },
        creator: {
            privileges: ['clueware_inpost_shipping.creator'],
            dependencies: ['clueware_inpost_shipping.viewer'],
        },
        editor: {
            privileges: ['clueware_inpost_shipping.editor'],
            dependencies: ['clueware_inpost_shipping.viewer'],
        },
        canceller: {
            privileges: ['clueware_inpost_shipping.canceller'],
            dependencies: ['clueware_inpost_shipping.viewer'],
        },
        labelDownloader: {
            privileges: ['clueware_inpost_shipping.label'],
            dependencies: ['clueware_inpost_shipping.viewer'],
        },
    },
});
