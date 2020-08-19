var dsvFileListPlugin = {
    attach: function (fileList) {
        if (fileList.id === 'trashbin' || fileList.id === 'files.public') {
            return;
        }

        fileList.registerTabView(new OCA.Dsv.DsvTabView());
    }
};
OC.Plugins.register('OCA.Files.FileList', dsvFileListPlugin);
