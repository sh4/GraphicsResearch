## 環境

- PHP 5.5 以上

## セットアップ

1. config.php.dist を config.php にリネームします
2. 画像保存用ディレクトリ (config.php 内の TEST_IMAGE_DIRECTORY) と回答データ保存用ディレクトリ (config.php 内の TEST_SESSION_DIRECTORY) を作成します
3. 用意したディレクトリに対して、PHP のプロセスから書き込みが行えるよう権限の調整を行います。
3. [任意] サーバーのファイルアップロード機能を利用する場合、 config.php の UPLOAD_KEY にランダムな文字列を指定します。

## ファイルの役割

- index.php
    - 回答用ページの表示
- answer.php
    - 回答データ (CSV) のダウンロード
- upload.php
    - サーバーへの画像ファイルアップロード
