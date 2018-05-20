# 用法
* MysqlBandReset.php是用来根据到期时间的日(day)来重置流量的
* ChartInfo.php是用来记录使用流量的，可不配置。

## 建议用法
0 0 * * * php -q /home/wwwroot/MysqlBandReset.php
1 */3 * * * php -q /home/wwwroot/ChartInfo.php