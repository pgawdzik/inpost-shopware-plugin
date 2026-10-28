import CluewareInpostPlugin from './plugin/clueware-inpost.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('CluewareInpost', CluewareInpostPlugin, '[data-clueware-inpost]');
