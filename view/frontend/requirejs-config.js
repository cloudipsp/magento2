var config = {
    paths: {
        '$checkout': 'https://unpkg.com/ipsp-js-sdk@1.0.15/dist/checkout.min'
    },
    shim: {
        '$checkout': {
            'exports': '$checkout'
        }
    }
};
