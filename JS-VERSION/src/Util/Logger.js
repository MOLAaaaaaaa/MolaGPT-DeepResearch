/**
 * Logger.js - 日志记录工具类
 * 
 * 提供简单的日志记录功能，支持控制台输出和文件记录
 */

const winston = require('winston');
const path = require('path');
const fs = require('fs');

class Logger {
    constructor() {
        // 确保日志目录存在
        const logDir = path.join(process.cwd(), 'logs');
        if (!fs.existsSync(logDir)) {
            fs.mkdirSync(logDir, { recursive: true });
        }

        // 创建日志文件路径
        const logFile = path.join(logDir, 'app.log');

        // 创建logger实例
        this.logger = winston.createLogger({
            level: 'info',
            format: winston.format.combine(
                winston.format.timestamp(),
                winston.format.json()
            ),
            transports: [
                new winston.transports.File({ filename: logFile }),
                new winston.transports.Console({
                    format: winston.format.combine(
                        winston.format.colorize(),
                        winston.format.simple()
                    )
                })
            ]
        });
    }

    /**
     * 设置日志级别
     * @param {string} level 日志级别 (debug, info, warn, error)
     */
    setLogLevel(level) {
        this.logger.level = level;
    }

    /**
     * 记录日志
     * @param {string} message 日志消息
     */
    log(message) {
        this.logger.info(message);
    }

    /**
     * 记录调试信息
     * @param {string} message 调试消息
     */
    debug(message) {
        this.logger.debug(message);
    }

    /**
     * 记录警告信息
     * @param {string} message 警告消息
     */
    warn(message) {
        this.logger.warn(message);
    }

    /**
     * 记录错误信息
     * @param {string} message 错误消息
     */
    error(message) {
        this.logger.error(message);
    }

    info(message) {
        this.logger.info(message);
    }
}

module.exports = Logger; 