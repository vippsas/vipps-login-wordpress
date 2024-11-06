const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require('path');

module.exports = {
  ...defaultConfig,
  entry: {
    'login-with-vipps-button/index': './login-with-vipps-button/index.ts',
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
    extensions: ['.tsx', '.ts', '.js'],
  },

  output: {
    path: path.resolve(__dirname, '../../blocks'),
  }
};