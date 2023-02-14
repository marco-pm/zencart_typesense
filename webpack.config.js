const path = require('path');

module.exports = [
    {
        name: 'typesense_dashboard',
        entry: path.resolve(__dirname, './src/typesense_dashboard.tsx'),
        output: {
            path: path.resolve(__dirname, './zc_plugins/Typesense/v1.0.0/admin/'),
            filename: 'typesense_dashboard.min.js',
        },
        module: {
            rules: [
                {
                    test: /\.tsx?$/,
                    use: 'ts-loader',
                    exclude: /node_modules/,
                },
            ],
        },
        resolve: {
            extensions: ['.tsx', '.ts', '.js', '.jsx']
        }
    }
];
