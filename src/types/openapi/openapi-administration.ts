/**
 * This file was auto-generated by openapi-typescript.
 * Do not make direct changes to the file.
 */

export type paths = {
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate/cfssl": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Generate certificate using CFSSL engine
         * @description This endpoint requires admin access
         */
        post: operations["admin-generate-certificate-cfssl"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate/openssl": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Generate certificate using OpenSSL engine
         * @description This endpoint requires admin access
         */
        post: operations["admin-generate-certificate-open-ssl"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Load certificate data
         * @description Return all data of root certificate and a field called `generated` with a boolean value.
         *     This endpoint requires admin access
         */
        get: operations["admin-load-certificate"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/configure-check": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Check the configuration of LibreSign
         * @description Return the status of necessary configuration and tips to fix the problems.
         *     This endpoint requires admin access
         */
        get: operations["admin-configure-check"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/disable-hate-limit": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Disable hate limit to current session
         * @description This will disable hate limit to current session.
         *     This endpoint requires admin access
         */
        get: operations["admin-disable-hate-limit"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/signature-background": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Get custom background image
         * @description This endpoint requires admin access
         */
        get: operations["admin-signature-background-get"];
        put?: never;
        /**
         * Add custom background image
         * @description This endpoint requires admin access
         */
        post: operations["admin-signature-background-save"];
        /**
         * Delete background image
         * @description This endpoint requires admin access
         */
        delete: operations["admin-signature-background-delete"];
        options?: never;
        head?: never;
        /**
         * Get custom background image
         * @description This endpoint requires admin access
         */
        patch: operations["admin-signature-background-reset"];
        trace?: never;
    };
    "/ocs/v2.php/apps/libresign/api/{apiVersion}/setting/has-root-cert": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Has root certificate
         * @description Checks whether the root certificate has been configured by checking the Nextcloud configuration table to see if the root certificate settings have
         */
        get: operations["setting-has-root-cert"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
};
export type webhooks = Record<string, never>;
export type components = {
    schemas: {
        CetificateDataGenerated: components["schemas"]["EngineHandler"] & {
            generated: boolean;
        };
        ConfigureCheck: {
            message: string;
            resource: string;
            /** @enum {string} */
            status: "error" | "success";
            tip: string;
        };
        EngineHandler: {
            configPath: string;
            cfsslUri?: string;
            rootCert: components["schemas"]["RootCertificate"];
        };
        OCSMeta: {
            status: string;
            statuscode: number;
            message?: string;
            totalitems?: string;
            itemsperpage?: string;
        };
        RootCertificate: {
            commonName: string;
            names: components["schemas"]["RootCertificateName"][];
        };
        RootCertificateName: {
            id: string;
            value: string;
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
};
export type $defs = Record<string, never>;
export interface operations {
    "admin-generate-certificate-cfssl": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description fields of root certificate */
                    rootCert: {
                        commonName: string;
                        names: {
                            [key: string]: {
                                value: string;
                            };
                        };
                    };
                    /**
                     * @description URI of CFSSL API
                     * @default
                     */
                    cfsslUri?: string;
                    /**
                     * @description Path of config files of CFSSL
                     * @default
                     */
                    configPath?: string;
                };
            };
        };
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                data: components["schemas"]["EngineHandler"];
                            };
                        };
                    };
                };
            };
            /** @description Account not found */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                message: string;
                            };
                        };
                    };
                };
            };
        };
    };
    "admin-generate-certificate-open-ssl": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description fields of root certificate */
                    rootCert: {
                        commonName: string;
                        names: {
                            [key: string]: {
                                value: string;
                            };
                        };
                    };
                    /**
                     * @description Path of config files of CFSSL
                     * @default
                     */
                    configPath?: string;
                };
            };
        };
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                data: components["schemas"]["EngineHandler"];
                            };
                        };
                    };
                };
            };
            /** @description Account not found */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                message: string;
                            };
                        };
                    };
                };
            };
        };
    };
    "admin-load-certificate": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["CetificateDataGenerated"];
                        };
                    };
                };
            };
        };
    };
    "admin-configure-check": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: components["schemas"]["ConfigureCheck"][];
                        };
                    };
                };
            };
        };
    };
    "admin-disable-hate-limit": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: unknown;
                        };
                    };
                };
            };
        };
    };
    "admin-signature-background-get": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Image returned */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "*/*": string;
                };
            };
            /** @description Image not found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "text/html": string;
                };
            };
        };
    };
    "admin-signature-background-save": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {string} */
                                status: "success";
                            };
                        };
                    };
                };
            };
            /** @description Error */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {string} */
                                status: "failure";
                                message: string;
                            };
                        };
                    };
                };
            };
        };
    };
    "admin-signature-background-delete": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Deleted with success */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {string} */
                                status: "success";
                            };
                        };
                    };
                };
            };
        };
    };
    "admin-signature-background-reset": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Image reseted to default */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                /** @enum {string} */
                                status: "success";
                            };
                        };
                    };
                };
            };
        };
    };
    "setting-has-root-cert": {
        parameters: {
            query?: never;
            header: {
                /** @description Required to be true for the API request to pass */
                "OCS-APIRequest": boolean;
            };
            path: {
                apiVersion: "v1";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description OK */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        ocs: {
                            meta: components["schemas"]["OCSMeta"];
                            data: {
                                hasRootCert: boolean;
                            };
                        };
                    };
                };
            };
        };
    };
}
