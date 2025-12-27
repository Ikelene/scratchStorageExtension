(function (Scratch) {
    'use strict';

    class ServerStorage {
        constructor() {
            this.serverUrl = 'https://ikelene.dev/storage/';
            this.apiKey = null;
            this.maxDataSize = 262144;
        }

        getInfo() {
            return {
                id: 'ikeleneServerStorage',
                name: 'Server Storage',
                color1: '#ff9bfd',
                color2: '#ff9bfd',
                color3: '#ff9bfd',
                docsURI: this.serverUrl + 'apiKey.html',
                blocks: [
                    {
                        blockType: Scratch.BlockType.LABEL,
                        text: 'press "Open Docs" to get API key'
                    },
                    {
                        opcode: 'setServerUrl',
                        blockType: Scratch.BlockType.COMMAND,
                        text: 'set server to [SERVER] server',
                        arguments: {
                            SERVER: {
                                type: Scratch.ArgumentType.STRING,
                                menu: 'serverMenu',
                                defaultValue: 'global'
                            }
                        }
                    },
                    {
                        opcode: 'setApiKey',
                        blockType: Scratch.BlockType.COMMAND,
                        text: 'set API key to [APIKEY]',
                        arguments: {
                            APIKEY: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'paste-your-api-key-here'
                            }
                        }
                    },
                    {
                        opcode: 'saveToServer',
                        blockType: Scratch.BlockType.COMMAND,
                        text: 'save [VALUE] to server as [KEY]',
                        arguments: {
                            VALUE: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'value'
                            },
                            KEY: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'key'
                            }
                        }
                    },
                    {
                        opcode: 'getFromServer',
                        blockType: Scratch.BlockType.REPORTER,
                        text: 'get [KEY] from server',
                        arguments: {
                            KEY: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'key'
                            }
                        }
                    },
                    {
                        opcode: 'serverDataExists',
                        blockType: Scratch.BlockType.BOOLEAN,
                        text: 'server has [KEY]',
                        arguments: {
                            KEY: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'key'
                            }
                        }
                    },
                    {
                        opcode: 'deleteFromServer',
                        blockType: Scratch.BlockType.COMMAND,
                        text: 'delete [KEY] from server',
                        arguments: {
                            KEY: {
                                type: Scratch.ArgumentType.STRING,
                                defaultValue: 'key'
                            }
                        }
                    },
                    {
                        opcode: 'isServerWorking',
                        blockType: Scratch.BlockType.BOOLEAN,
                        text: 'is server working?'
                    }
                ],
                menus: {
                    serverMenu: {
                        acceptReporters: true,
                        items: ['global']
                    }
                }
            };
        }

        setServerUrl(args) {
            const server = args.SERVER;
            if (server === 'global') {
                this.serverUrl = 'https://ikelene.dev/storage/';
            } else {
                const trimmed = server.trim();
                if (trimmed.length === 0) {
                    return;
                }
                this.serverUrl = trimmed.endsWith('/') ? trimmed : trimmed + '/';
            }
        }

        setApiKey(args) {
            this.apiKey = args.APIKEY.trim();
        }

        hasValidKey() {
            return this.apiKey && this.apiKey.length > 0;
        }

        async saveToServer(args) {
            if (!this.hasValidKey()) {
                console.warn('You need to set an API key first. You can generate a key if you press the Open Documentation button');
                return;
            }

            const value = args.VALUE;
            const key = args.KEY;
            const size = new TextEncoder().encode(value).length;

            if (size > this.maxDataSize) {
                return;
            }

            const payload = {
                apiKey: this.apiKey,
                key: key,
                value: value,
                mimeType: 'application/json'
            };

            try {
                const response = await fetch(this.serverUrl + 'store.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!result.success) {
                    console.warn('You need to set an API key first. You can generate a key if you press the Open Documentation button');
                }
            } catch (error) {
                console.warn('You need to set an API key first. You can generate a key if you press the Open Documentation button');
            }
        }

        async getFromServer(args) {
            if (!this.hasValidKey()) {
                return 'You need to set an API key first. You can generate a key if you press the Open Documentation button';
            }

            const key = args.KEY;

            try {
                const response = await fetch(this.serverUrl + 'get.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        apiKey: this.apiKey,
                        key: key
                    })
                });

                if (response.status === 401) {
                    return 'You need to set an API key first. You can generate a key if you press the Open Documentation button';
                }

                const result = await response.json();
                if (result.success && result.data) {
                    return result.data.value || '';
                }
                return '';
            } catch (error) {
                return 'You need to set an API key first. You can generate a key if you press the Open Documentation button';
            }
        }

        async serverDataExists(args) {
            if (!this.hasValidKey()) {
                return false;
            }

            const key = args.KEY;

            try {
                const response = await fetch(this.serverUrl + 'get.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        apiKey: this.apiKey,
                        key: key
                    })
                });

                if (response.status === 401) {
                    return false;
                }

                const result = await response.json();
                return result.success && !!result.data;
            } catch (error) {
                return false;
            }
        }

        async deleteFromServer(args) {
            if (!this.hasValidKey()) {
                console.warn('You need to set an API key first. You can generate a key if you press the Open Documentation button');
                return;
            }

            const key = args.KEY;

            try {
                const response = await fetch(this.serverUrl + 'delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        apiKey: this.apiKey,
                        key: key
                    })
                });

                const result = await response.json();
                if (!result.success) {
                    console.warn('Delete failed or key not found');
                }
            } catch (error) {
                console.warn('Delete failed or server unreachable');
            }
        }

        async isServerWorking() {
            try {
                const response = await fetch(this.serverUrl + 'ping.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    return false;
                }

                const result = await response.json();
                return result.success === true && result.status === 'ok';
            } catch (error) {
                return false;
            }
        }
    }

    Scratch.extensions.register(new ServerStorage());
})(Scratch);
