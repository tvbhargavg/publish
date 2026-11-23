GO := docker compose run --rm go
.PHONY: tidy test test-race vet fmt build up down
tidy:      ; $(GO) sh -c 'go get github.com/hashicorp/yamux@latest 2>/dev/null; go mod tidy'
test:      ; $(GO) go test ./...
test-race: ; $(GO) go test -race ./...
vet:       ; $(GO) go vet ./...
fmt:       ; $(GO) gofmt -l -w .
build:     ; $(GO) sh -c 'mkdir -p bin && go build -o bin/ ./cmd/...'
up:        ; docker compose up -d postgres redis
down:      ; docker compose down
