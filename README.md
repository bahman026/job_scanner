
## About job_scanner

Search for job opportunities in the top companies on jobinja and jobvision


## How to use
Just run the following command
```bash
php artisan app:job-scan keyword
```
With the above command, you will get a list of job opportunities that contain the `keyword`.
The output is saved in the `result.json`.

You can also use up to 4 keywords.
```bash
php artisan app:job-scan php laravel back-end python
```
## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
