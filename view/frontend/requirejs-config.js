var config = {
    paths: {
        '$checkout': 'https://unpkg.com/ipsp-js-sdk@latest/dist/checkout.min'
    },
    shim: {
        '$checkout': {
            'exports': '$checkout'
        }
    }
};
