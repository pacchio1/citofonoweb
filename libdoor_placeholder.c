#include <stdio.h>
#include <stdlib.h>

void pin_on(short pin){
    fprintf(stderr,"%d : on\n",pin);
}

void pin_off(short pin){
    fprintf(stderr,"%d : off\n",pin);
}

int read_pin(short pin){
    return 0;
}