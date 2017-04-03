test:
	vendor/bin/phpunit

MKDOCS := $(shell mkdocs -V)

mkdocs:
ifndef MKDOCS
	pip install mkdocs
endif
	mkdocs build --clean
