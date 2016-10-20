using System;

namespace cslib
{
    using Mono.Cecil;

    public interface IInstrumenter
    {
        /// <summary>
        /// Instruments the assembly.
        /// </summary>
        /// <returns>The number of instructions instrumented.</returns>
        int InstrumentAssembly(AssemblyDefinition assembly, string output, Action<int, int, string> instructionInstrumented);
    }
}

