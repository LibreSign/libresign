(function () {
    var DsvTabView = OCA.Files.DetailTabView.extend({
        id: 'dsvTabView',
        className: 'tab dsvTabView',

        getLabel: function () {
            return 'DSV';
        },

        getIcon: function () {
            return 'icon-details';
        },

        render: function () {
            var fileInfo = this.getFileInfo();

            if (fileInfo) {
                this.$el.html('<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="'
                    + OC.imagePath('core', 'loading.gif')
                    + '"><br><br></p><p>'
                    + 'Lendo assinatura digital …'
                    + '</p></div>');

                var url = OC.generateUrl('/apps/dsv/get'),
                    data = { source: fileInfo.getFullPath() },
                    _self = this;
                $.ajax({
                    type: 'GET',
                    url: url,
                    dataType: 'json',
                    data: data,
                    async: true,
                    success: function (data) {
                        _self.updateDisplay(data);
                    }
                });
            }
        },

        canDisplay: function (fileInfo) {
            if (!fileInfo || fileInfo.isDirectory()) {
                return false;
            }

            var mimetype = fileInfo.get('mimetype') || '';

            return mimetype === "application/pdf";
        },

        updateDisplay: function (data) {
            const tabView = this.$el.find('.get-metadata');

            tabView.empty()

            if (data.response !== 'success') {
                var msg = $('<p>').text(data.msg);

                tabView.append(msg);
                return
            }

            var signatures = data.signatures;
            for (const key in signatures) {
                if (!signatures.hasOwnProperty(key)) {
                    continue;

                }
                const signature = signatures[key];
                var signatureElement = $('<details open>').addClass('signature');
                signatureElement
                    .append($('<summary>').text(key))

                for (const prop in signature) {
                    if (!signature.hasOwnProperty(prop)) {
                        continue;
                    }
                    const label = signature[prop].label
                    const value = signature[prop].value

                    signatureElement.append(
                        $('<details open>')
                            .append($('<summary>').text(label))
                            .append($('<p>').text(value))
                    );
                }


                tabView.append(signatureElement);
            }

        },

        addCertRow: function (container, cert) {
            for (const certProp in cert) {
                if (!cert.hasOwnProperty(certProp)) {
                    continue;
                }

                container.append(
                    $('<details>').addClass('certRow')
                        .append($('<summary>').text(certProp))
                        .append($('<p>').text(cert[certProp]))
                );
            }

            return container;
        },

        add: function (val, array) {
            if (val) {
                array.push(val);
            }
        },
    });

    OCA.Dsv = OCA.Dsv || {};

    OCA.Dsv.DsvTabView = DsvTabView;
})();
