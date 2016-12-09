# Set the default behavior, in case people don't have core.autocrlf set.
* eol=lf
* text=auto

# Denote all files that are truly binary and should not be modified.
*.png binary
*.jpg binary
*.gif binary
*.jpeg binary
*.zip binary
*.phar binary
*.ttf binary
*.woff binary
*.eot binary
*.ico binary
*.mo binary
*.pdf binary

# Remove files for archives generated using `git archive`
codeception.yml export-ignore
dependency.json export-ignore
.coveralls.yml export-ignore
.travis.yml export-ignore
.editorconfig export-ignore
.gitattributes export-ignore
.gitignore export-ignore
