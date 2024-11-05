const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require('path');

module.exports = {
  ...defaultConfig,
  entry: {
    'login-with-vipps-button/index': './login-with-vipps-button/index.js',
  },
  output: {
    path: path.resolve(__dirname, '../../blocks'),
    filename: '[name].js'
  }
};