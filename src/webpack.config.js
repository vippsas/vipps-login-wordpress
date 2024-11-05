const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require('path');

module.exports = {
  ...defaultConfig,
  output: {
    path: path.resolve(__dirname, '../blocks'),
    filename: '[name].js'
  }
};